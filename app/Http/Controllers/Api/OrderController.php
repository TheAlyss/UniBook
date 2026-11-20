<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Listing;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Module C (Orders, member C): orders. Internal data only, no external API.
 * Payment is cash at the meetup.
 */
class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderService $orders)
    {
    }

    /** GET /api/orders: my purchases and my sales */
    public function index(Request $request)
    {
        $user = $request->user();
        $with = ['buyer', 'seller', 'meetup'];

        return $this->ok([
            'purchases' => OrderResource::collection($user->purchases()->with($with)->latest()->get()),
            'sales' => OrderResource::collection($user->sales()->with($with)->latest()->get()),
        ]);
    }

    /** GET /api/orders/{id} */
    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        return $this->ok(new OrderResource($order->load('buyer', 'seller', 'meetup')));
    }

    /** POST /api/orders  (C: Create) the buyer books a listing */
    public function store(Request $request)
    {
        $data = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'listing_id.exists' => 'This listing does not exist.',
        ]);

        try {
            $order = $this->orders->place(Listing::findOrFail($data['listing_id']), $request->user(), $data['note'] ?? null);
        } catch (OrderException $e) {
            return $this->fail($e->getMessage(), $e->status());
        }

        return $this->ok(
            new OrderResource($order->load('buyer', 'seller', 'meetup')),
            'Book reserved. Next, set a meetup with POST /api/meetups. Pay the seller in cash when you meet.',
            201
        );
    }

    /**
     * PUT /api/orders/{id}  (C: Update)
     *   {"status": "completed"}            seller: meetup done, cash received
     *   {"status": "cancelled"}            buyer or seller, while booked
     *   {"rating": 1-5, "review": "..."}   buyer, after the order is completed
     */
    public function update(Request $request, Order $order)
    {
        Gate::authorize('view', $order);

        $data = $request->validate([
            'status' => ['required_without:rating', 'in:completed,cancelled'],
            'rating' => ['required_without:status', 'integer', 'between:1,5'],
            'review' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required_without' => 'Send a status ("completed" or "cancelled") or a rating.',
            'status.in' => 'The status must be "completed" or "cancelled".',
            'rating.required_without' => 'Send a status ("completed" or "cancelled") or a rating.',
            'rating.between' => 'The rating must be between 1 and 5.',
        ]);

        try {
            $user = $request->user();
            $message = 'Order updated.';

            if (isset($data['status'])) {
                $order = $data['status'] === 'completed'
                    ? $this->orders->complete($order, $user)
                    : $this->orders->cancel($order, $user);
                $message = $data['status'] === 'completed'
                    ? 'Order marked as completed. The buyer can now rate you.'
                    : 'Order cancelled. The book is available again.';
            }
            if (isset($data['rating'])) {
                $order = $this->orders->rate($order, $user, (int) $data['rating'], $data['review'] ?? null);
                $message = 'Thank you for rating the seller.';
            }
        } catch (OrderException $e) {
            return $this->fail($e->getMessage(), $e->status());
        }

        return $this->ok(new OrderResource($order->load('buyer', 'seller', 'meetup')), $message);
    }
}
