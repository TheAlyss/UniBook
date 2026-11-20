<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MeetupController as WebMeetupController;
use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use App\Models\Order;
use App\Services\MapsService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Module C (Orders, member C): meetups. Uses OpenStreetMap through MapsService.
 */
class MeetupController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderService $orders)
    {
    }

    /** GET /api/meetups/suggest?place=Arau  or  ?lat=..&lng=..  (helper: all points sorted by distance) */
    public function suggest(Request $request, MapsService $maps)
    {
        $result = WebMeetupController::suggestions($request, $maps);
        $body = $result['body'];
        $message = $body['message'];
        unset($body['message']);

        return $result['status'] === 200 ? $this->ok($body, $message) : $this->fail($message, $result['status']);
    }

    /**
     * POST /api/meetups  (C: Create, Nominatim)
     * Sets (or changes) the meetup for an order. Give "place" (looked up with Nominatim) or lat/lng.
     * Leave out "meetup_point" to get the nearest campus meetup point automatically.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'meetup_time' => ['required', 'date', 'after:now', 'before:+14 days'],
            'meetup_point' => ['nullable', 'in:' . implode(',', array_keys(config('unibook.meetup_points')))],
            'place' => ['nullable', 'string', 'min:2', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ], [
            'order_id.exists' => 'This order does not exist.',
            'meetup_point.in' => 'Unknown meetup point. Valid points: ' . implode(', ', array_keys(config('unibook.meetup_points'))) . '.',
            'meetup_time.after' => 'The meetup time must be in the future.',
            'meetup_time.before' => 'The meetup time must be within the next 14 days.',
        ]);

        $order = Order::findOrFail($data['order_id']);
        Gate::authorize('view', $order);

        try {
            [$meetup, $created] = $this->orders->setMeetup($order, $request->user(), [
                'meetup_time' => $data['meetup_time'],
                'meetup_point' => $data['meetup_point'] ?? null,
                'from_place' => $data['place'] ?? null,
                'from_lat' => $data['lat'] ?? null,
                'from_lng' => $data['lng'] ?? null,
            ]);
        } catch (OrderException $e) {
            return $this->fail($e->getMessage(), $e->status());
        }

        return $this->ok(
            new MeetupResource($meetup),
            ($created ? 'Meetup set at ' : 'Meetup changed to ') . $meetup->name . '.',
            $created ? 201 : 200
        );
    }

    /**
     * GET /api/meetups/{id}/route  (C: Read, OSRM)
     * Road distance, travel time and route line from the buyer's starting point
     * (or from ?lat=..&lng=..) to the meetup point.
     */
    public function route(Request $request, Meetup $meetup)
    {
        Gate::authorize('view', $meetup->order);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ]);

        try {
            $route = $this->orders->route(
                $meetup,
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (OrderException $e) {
            return $this->fail($e->getMessage(), $e->status());
        }

        return $this->ok($route, $route['method'] === 'road'
            ? 'Route calculated with OSRM.'
            : 'Route service unavailable: showing straight-line distance.');
    }
}
