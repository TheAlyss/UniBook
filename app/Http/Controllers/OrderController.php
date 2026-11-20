<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Models\Listing;
use App\Models\Order;
use App\Services\MapsService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Module C (Orders) for the website:
 * book + meetup -> meet and pay cash -> seller marks completed -> buyer rates.
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return view('orders.index', [
            'purchases' => $user->purchases()->with('seller', 'meetup', 'listing')->latest()->get(),
            'sales' => $user->sales()->with('buyer', 'meetup', 'listing')->latest()->get(),
        ]);
    }

    /** Booking page: choose a meetup point (suggested by distance) and a time. */
    public function create(Request $request, Listing $listing, MapsService $maps)
    {
        if ($listing->user_id === $request->user()->id) {
            return redirect()->route('books.show', $listing)->with('error', 'You cannot book your own listing.');
        }
        if ($listing->status !== 'available' || $listing->isHiddenFromPublic()) {
            abort_if($listing->isHiddenFromPublic(), 404);

            return redirect()->route('books.show', $listing)->with('error', 'Sorry, this book is no longer available.');
        }

        return view('orders.create', [
            'listing' => $listing->load('user'),
            'points' => array_values($maps->meetupPoints()),
            'campus' => config('unibook.campus'),
        ]);
    }

    /** Creates the order and its meetup together (one form on the website). */
    public function store(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'meetup_point' => ['required', 'in:' . implode(',', array_keys(config('unibook.meetup_points')))],
            'meetup_time' => ['required', 'date', 'after:now', 'before:+14 days'],
            'from_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:from_lng'],
            'from_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:from_lat'],
            'from_place' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'meetup_point.required' => 'Please choose a meetup point.',
            'meetup_point.in' => 'Please choose a meetup point from the list.',
            'meetup_time.after' => 'The meetup time must be in the future.',
            'meetup_time.before' => 'The meetup time must be within the next 14 days.',
        ]);

        try {
            $order = DB::transaction(function () use ($listing, $request, $data) {
                $order = $this->orders->place($listing, $request->user(), $data['note'] ?? null);
                $this->orders->setMeetup($order, $request->user(), [
                    'meetup_point' => $data['meetup_point'],
                    'meetup_time' => $data['meetup_time'],
                    'from_lat' => $data['from_lat'] ?? null,
                    'from_lng' => $data['from_lng'] ?? null,
                    'from_place' => isset($data['from_lat']) ? ($data['from_place'] ?? null) : null,
                ]);

                return $order;
            });
        } catch (OrderException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Book reserved for you! Meet the seller and pay in cash.');
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        $order->load('buyer', 'seller', 'listing', 'meetup');

        return view('orders.show', [
            'order' => $order,
            'sellerRating' => $order->seller->sellerRating(),
        ]);
    }

    /** Seller: the meetup happened and the cash was received. */
    public function complete(Request $request, Order $order)
    {
        return $this->act($order, fn () => $this->orders->complete($order, $request->user()),
            'Order marked as completed. Thank you for using UniBook!');
    }

    public function cancel(Request $request, Order $order)
    {
        return $this->act($order, fn () => $this->orders->cancel($order, $request->user()),
            'The order has been cancelled and the book is available again.');
    }

    /** Buyer rates the seller after the order is completed. */
    public function rate(Request $request, Order $order)
    {
        Gate::authorize('view', $order);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review' => ['nullable', 'string', 'max:500'],
        ], [
            'rating.required' => 'Please rate the seller from 1 to 5 stars.',
        ]);

        return $this->act($order, fn () => $this->orders->rate($order, $request->user(), (int) $data['rating'], $data['review'] ?? null),
            'Thank you! Your rating has been saved.');
    }

    private function act(Order $order, callable $action, string $success)
    {
        Gate::authorize('view', $order);

        try {
            $action();
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('success', $success);
    }
}
