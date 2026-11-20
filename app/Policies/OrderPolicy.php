<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /** Only the buyer and the seller can see an order. Finer rules live in OrderService. */
    public function view(User $user, Order $order): Response
    {
        return $order->isParty($user)
            ? Response::allow()
            : Response::deny('You can only view your own orders.');
    }
}
