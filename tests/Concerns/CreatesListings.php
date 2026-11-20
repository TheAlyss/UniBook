<?php

namespace Tests\Concerns;

use App\Models\Listing;
use App\Models\Meetup;
use App\Models\Order;
use App\Models\User;

/** Small helpers so tests don't repeat listing/order setup. */
trait CreatesListings
{
    protected function makeListing(?User $seller = null, array $overrides = []): Listing
    {
        $seller ??= User::factory()->create();

        return $seller->listings()->create($overrides + [
            'title' => 'RESTful Web APIs',
            'author' => 'Leonard Richardson',
            'course_code' => 'ICT651',
            'category' => 'Textbook',
            'price' => 40,
            'condition' => 'Good',
            'status' => 'available',
        ]);
    }

    protected function makeOrder(?User $buyer = null, ?Listing $listing = null, string $status = 'pending', bool $withMeetup = false): Order
    {
        $listing ??= $this->makeListing();
        $buyer ??= User::factory()->create();

        $listing->update(['status' => $status === 'completed' ? 'sold' : ($status === 'pending' ? 'reserved' : 'available')]);

        $order = Order::create([
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'buyer_id' => $buyer->id,
            'seller_id' => $listing->user_id,
            'amount' => $listing->price,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        if ($withMeetup) {
            Meetup::create([
                'order_id' => $order->id,
                'point' => 'library',
                'name' => "Dato' Jaafar Hassan Library",
                'lat' => 6.4495257,
                'lng' => 100.2779053,
                'meetup_time' => now()->addDay(),
                'from_lat' => 6.44,
                'from_lng' => 100.27,
            ]);
        }

        return $order;
    }
}
