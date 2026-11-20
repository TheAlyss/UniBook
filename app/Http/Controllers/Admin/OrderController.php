<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

/** Admin: every order; cancel one that is stuck (the book becomes available again). */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $orders = Order::with(['buyer', 'seller', 'meetup'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('listing_title', 'like', "%{$q}%")
                ->orWhereHas('buyer', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                ->orWhereHas('seller', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))))
            ->when(array_key_exists($status, Order::STATUSES), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'q', 'status'));
    }

    public function cancel(Request $request, Order $order, OrderService $service)
    {
        try {
            $service->cancel($order, $request->user());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order #{$order->id} was cancelled; the book is available again.");
    }
}
