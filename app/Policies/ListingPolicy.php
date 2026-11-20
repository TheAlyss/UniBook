<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ListingPolicy
{
    /**
     * Only the seller who posted a listing can edit or delete it.
     */
    public function update(User $user, Listing $listing): Response
    {
        return $user->id === $listing->user_id
            ? Response::allow()
            : Response::deny('You can only manage your own listings.');
    }

    public function delete(User $user, Listing $listing): Response
    {
        return $this->update($user, $listing);
    }
}
