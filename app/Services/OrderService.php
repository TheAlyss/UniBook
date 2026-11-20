<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Listing;
use App\Models\Meetup;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Module C (Orders) business rules, shared by the website and the REST API.
 * Payment is cash at the meetup, so there is no payment step in the system.
 *
 *   place()      available listing -> order "pending", listing "reserved"
 *   setMeetup()  meetup point + time (Nominatim for place names, OSRM for distance)
 *   complete()   SELLER confirms the meetup happened and cash was received -> "completed", listing "sold"
 *   cancel()     buyer or seller, while pending -> "cancelled", listing "available" again
 *   rate()       BUYER rates the seller after the order is completed
 */
class OrderService
{
    public function __construct(private MapsService $maps)
    {
    }

    public function place(Listing $listing, User $buyer, ?string $note = null): Order
    {
        return DB::transaction(function () use ($listing, $buyer, $note) {
            // Lock the row so two buyers cannot book the same book at the same time.
            $listing = Listing::whereKey($listing->id)->lockForUpdate()->firstOrFail();

            if ($listing->user_id === $buyer->id) {
                throw new OrderException('You cannot book your own listing.', 403);
            }
            if ($listing->status !== 'available') {
                throw new OrderException('Sorry, this book has already been booked by someone else.');
            }
            if ($listing->isHiddenFromPublic()) {
                throw new OrderException('Sorry, this book is no longer available.');
            }

            $order = Order::create([
                'listing_id' => $listing->id,
                'listing_title' => $listing->title,
                'buyer_id' => $buyer->id,
                'seller_id' => $listing->user_id,
                'amount' => $listing->price,
                'status' => 'pending',
                'note' => $note,
            ]);

            $listing->update(['status' => 'reserved']);

            return $order;
        });
    }

    /**
     * Create or change the meetup for an order.
     *
     * @param array{meetup_time:string|Carbon, meetup_point?:?string, from_lat?:?float, from_lng?:?float, from_place?:?string} $data
     * @return array{0: Meetup, 1: bool} the meetup and whether it was newly created
     */
    public function setMeetup(Order $order, User $user, array $data): array
    {
        if (! $order->isParty($user)) {
            throw new OrderException('You can only arrange meetups for your own orders.', 403);
        }
        if ($order->status !== 'pending') {
            throw new OrderException('The meetup can only be changed while the order is still booked.');
        }

        // Where is the buyer starting from? (optional)
        $from = null;
        if (isset($data['from_lat'], $data['from_lng'])) {
            $from = ['lat' => (float) $data['from_lat'], 'lng' => (float) $data['from_lng'], 'name' => $data['from_place'] ?? null];
        } elseif (! empty($data['from_place'])) {
            $from = $this->maps->geocode($data['from_place'])
                ?? throw new OrderException('We could not find that place. Try a nearby landmark, e.g. "Arau" or "Kangar".', 404);
        }

        // Which meetup point? Chosen by the user, or the nearest one to the buyer.
        $key = $data['meetup_point'] ?? null;
        if ($key === null) {
            if ($from === null) {
                throw new OrderException('Choose a meetup point, or share your location so we can suggest the nearest one.', 422);
            }
            $key = $this->maps->suggestMeetupPoints($from['lat'], $from['lng'])[0]['key'];
        }
        $point = $this->maps->meetupPoint($key)
            ?? throw new OrderException('Please choose a valid meetup point.', 422);

        $route = $from ? $this->maps->route($from['lat'], $from['lng'], $point['lat'], $point['lng']) : null;

        $meetup = Meetup::updateOrCreate(['order_id' => $order->id], [
            'point' => $point['key'],
            'name' => $point['name'],
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'meetup_time' => Carbon::parse($data['meetup_time']),
            'from_place' => $from['name'] ?? null,
            'from_lat' => $from['lat'] ?? null,
            'from_lng' => $from['lng'] ?? null,
            'distance_m' => $route['distance_m']
                ?? ($from ? MapsService::straightLineMetres($from['lat'], $from['lng'], $point['lat'], $point['lng']) : null),
            'duration_s' => $route['duration_s'] ?? null,
        ]);

        return [$meetup, $meetup->wasRecentlyCreated];
    }

    /**
     * Route from the buyer's starting point (or a given point) to the meetup, using OSRM.
     * Falls back to a straight-line distance if OSRM is unavailable.
     */
    public function route(Meetup $meetup, ?float $fromLat = null, ?float $fromLng = null): array
    {
        $useSaved = $fromLat === null || $fromLng === null;
        if ($useSaved) {
            if (! $meetup->hasOrigin()) {
                throw new OrderException('This meetup has no starting point. Send lat and lng to get a route.', 422);
            }
            [$fromLat, $fromLng] = [$meetup->from_lat, $meetup->from_lng];
        }

        $route = $this->maps->route($fromLat, $fromLng, $meetup->lat, $meetup->lng);

        $result = $route
            ? $route + ['method' => 'road']
            : [
                'distance_m' => MapsService::straightLineMetres($fromLat, $fromLng, $meetup->lat, $meetup->lng),
                'duration_s' => null,
                'geometry' => [],
                'method' => 'straight_line',
            ];

        if ($useSaved && $route) {
            $meetup->update(['distance_m' => $route['distance_m'], 'duration_s' => $route['duration_s']]);
        }

        return ['from' => ['lat' => $fromLat, 'lng' => $fromLng], 'to' => ['name' => $meetup->name, 'lat' => $meetup->lat, 'lng' => $meetup->lng]] + $result;
    }

    /** Seller marks the order as done: the meetup happened and the cash was received. */
    public function complete(Order $order, User $user): Order
    {
        if ($order->seller_id !== $user->id) {
            throw new OrderException('Only the seller can mark the order as completed.', 403);
        }
        if ($order->status !== 'pending') {
            throw new OrderException('Only booked orders can be marked as completed.');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'completed', 'completed_at' => now()]);
            $order->listing?->update(['status' => 'sold']);
        });

        return $order->fresh();
    }

    /** The buyer, the seller or an admin (e.g. an order stuck because a party disappeared). */
    public function cancel(Order $order, User $user): Order
    {
        if (! $order->isParty($user) && ! $user->isAdmin()) {
            throw new OrderException('You cannot cancel this order.', 403);
        }
        if ($order->status !== 'pending') {
            throw new OrderException('Only booked orders can be cancelled.');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $order->listing?->update(['status' => 'available']);
        });

        return $order->fresh();
    }

    /** Buyer rates the seller once, after the seller has marked the order as completed. */
    public function rate(Order $order, User $user, int $rating, ?string $review = null): Order
    {
        if ($order->buyer_id !== $user->id) {
            throw new OrderException('Only the buyer can rate the seller.', 403);
        }
        if ($order->status !== 'completed') {
            throw new OrderException('You can rate the seller after the seller marks the order as completed.');
        }
        if ($order->rating !== null) {
            throw new OrderException('You have already rated this order.');
        }

        $order->update(['rating' => $rating, 'review' => $review, 'rated_at' => now()]);

        return $order->fresh();
    }
}
