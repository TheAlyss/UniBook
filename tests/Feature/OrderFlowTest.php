<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesListings;
use Tests\TestCase;

/**
 * Website orders flow: book + meetup -> seller marks completed (cash) -> buyer rates.
 */
class OrderFlowTest extends TestCase
{
    use CreatesListings, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'router.project-osrm.org/route/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 850, 'duration' => 120, 'geometry' => ['coordinates' => [[100.27, 6.44], [100.2779, 6.4495]]]]],
            ]),
        ]);
    }

    private function bookingData(array $overrides = []): array
    {
        return $overrides + [
            'meetup_point' => 'library',
            'meetup_time' => now()->addDay()->format('Y-m-d H:i'),
            'from_lat' => 6.44,
            'from_lng' => 100.27,
            'from_place' => 'Your location',
            'note' => 'Blue shirt',
        ];
    }

    public function test_full_flow_book_complete_and_rate(): void
    {
        $listing = $this->makeListing();
        $buyer = User::factory()->create();

        // 1. Buyer books and sets the meetup
        $this->actingAs($buyer)->post(route('orders.store', $listing), $this->bookingData())->assertRedirect();
        $order = Order::with('meetup')->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('library', $order->meetup->point);
        $this->assertSame(850, $order->meetup->distance_m);
        $this->assertSame('reserved', $listing->fresh()->status);
        $this->get(route('home'))->assertDontSee('RESTful Web APIs'); // hidden from search
        $this->get(route('orders.show', $order))->assertSee('Bring')->assertDontSee('Mark as Completed');

        // 2. Seller marks it completed after meeting and receiving cash
        $this->actingAs($listing->user)->get(route('orders.show', $order))->assertSee('Mark as Completed');
        $this->post(route('orders.complete', $order))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('sold', $listing->fresh()->status);

        // 3. Buyer rates the seller
        $this->actingAs($buyer)->post(route('orders.rate', $order), ['rating' => 5, 'review' => 'Great seller'])->assertRedirect();
        $this->assertSame(5, $order->fresh()->rating);
        $this->assertSame(['average' => 5.0, 'count' => 1], $listing->user->sellerRating());
    }

    public function test_buyer_cannot_mark_order_completed(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($order->buyer)->post(route('orders.complete', $order))
            ->assertSessionHas('error', 'Only the seller can mark the order as completed.');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_rating_only_after_completion_and_only_once(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($order->buyer)->post(route('orders.rate', $order), ['rating' => 4])
            ->assertSessionHas('error', 'You can rate the seller after the seller marks the order as completed.');

        $order->update(['status' => 'completed', 'completed_at' => now()]);
        $this->post(route('orders.rate', $order), ['rating' => 4])->assertSessionHas('success');
        $this->post(route('orders.rate', $order), ['rating' => 1])->assertSessionHas('error', 'You have already rated this order.');
        $this->assertSame(4, $order->fresh()->rating);
    }

    public function test_seller_cannot_book_own_listing(): void
    {
        $listing = $this->makeListing();

        $this->actingAs($listing->user)->post(route('orders.store', $listing), $this->bookingData())
            ->assertSessionHas('error', 'You cannot book your own listing.');
        $this->assertSame(0, Order::count());
    }

    public function test_second_buyer_cannot_book_a_reserved_listing(): void
    {
        $order = $this->makeOrder();

        $this->actingAs(User::factory()->create())
            ->post(route('orders.store', $order->listing), $this->bookingData())
            ->assertSessionHas('error', 'Sorry, this book has already been booked by someone else.');
        $this->assertSame(1, Order::count());
    }

    public function test_booking_validation_messages(): void
    {
        $listing = $this->makeListing();

        $this->actingAs(User::factory()->create())
            ->post(route('orders.store', $listing), ['meetup_point' => 'mars', 'meetup_time' => now()->subDay()->format('Y-m-d H:i')])
            ->assertSessionHasErrors([
                'meetup_point' => 'Please choose a meetup point from the list.',
                'meetup_time' => 'The meetup time must be in the future.',
            ]);
        $this->assertSame('available', $listing->fresh()->status);
    }

    public function test_cancel_makes_the_listing_available_again(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($order->buyer)->post(route('orders.cancel', $order))->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('available', $order->listing->fresh()->status);
    }

    public function test_only_parties_can_view_an_order(): void
    {
        $order = $this->makeOrder(withMeetup: true);

        $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($order->seller)->get(route('orders.show', $order))->assertOk()->assertSee("Dato&#039; Jaafar Hassan Library", false);
    }

    public function test_reserved_listing_cannot_be_deleted_or_unreserved_by_seller(): void
    {
        $order = $this->makeOrder();
        $listing = $order->listing;

        $this->actingAs($order->seller)->delete(route('listings.destroy', $listing))->assertSessionHas('error');
        $this->assertNotNull($listing->fresh());

        $this->put(route('listings.update', $listing), [
            'title' => 'New title', 'author' => 'A', 'course' => 'ICT651', 'category' => 'Textbook', 'price' => 30, 'condition' => 'Good', 'status' => 'available',
        ])->assertRedirect();
        $this->assertSame('reserved', $listing->fresh()->status);
        $this->assertSame('New title', $listing->fresh()->title);
    }
}
