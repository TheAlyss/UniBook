<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /** Only the buyer, the seller and admins can see an order. Finer rules live in OrderService. */
    public function view(User $user, Order $order): Response
    {
        return $order->isParty($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('You can only view your own orders.');
    }
}
