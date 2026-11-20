<?php

namespace App\Http\Controllers;

use App\Services\MapsService;
use Illuminate\Http\Request;

/**
 * Meetup suggestions for the booking page (JSON).
 * Location comes from the browser (lat/lng) or a typed place name (Nominatim).
 */
class MeetupController extends Controller
{
    public function suggest(Request $request, MapsService $maps)
    {
        $result = self::suggestions($request, $maps);

        return response()->json($result['body'], $result['status']);
    }

    /** Shared with the API. Returns ['status' => int, 'body' => array]. */
    public static function suggestions(Request $request, MapsService $maps): array
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng', 'required_without:place'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'place' => ['nullable', 'string', 'min:2', 'max:100'],
        ], [
            'lat.required_without' => 'Please share your location or type where you are.',
        ]);

        $origin = null;
        if (isset($data['lat'], $data['lng'])) {
            $origin = ['lat' => (float) $data['lat'], 'lng' => (float) $data['lng'], 'name' => 'Your location'];
        } else {
            $origin = $maps->geocode($data['place']);
            if ($origin === null) {
                return ['status' => 404, 'body' => [
                    'message' => 'We could not find that place. Try a nearby landmark, e.g. "Arau" or "Kangar".',
                ]];
            }
        }

        $points = $maps->suggestMeetupPoints($origin['lat'], $origin['lng']);
        $best = $points[0];
        $route = $maps->route($origin['lat'], $origin['lng'], $best['lat'], $best['lng']);

        return ['status' => 200, 'body' => [
            'message' => "Nearest meetup point: {$best['name']}.",
            'origin' => $origin,
            'points' => $points,
            'route' => $route,
        ]];
    }

    /** Route line from the buyer to one chosen point (when the buyer picks a different point). */
    public function route(Request $request, MapsService $maps)
    {
        $data = $request->validate([
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'point' => ['required', 'in:' . implode(',', array_keys(config('unibook.meetup_points')))],
        ]);

        $point = $maps->meetupPoint($data['point']);
        $route = $maps->route((float) $data['from_lat'], (float) $data['from_lng'], $point['lat'], $point['lng']);

        return response()->json(['point' => $point, 'route' => $route], $route ? 200 : 503);
    }
}
