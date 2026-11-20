<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesListings;
use Tests\TestCase;

/**
 * REST API (/api): gateway behaviour + the 8 planned endpoints.
 */
class ApiTest extends TestCase
{
    use CreatesListings, RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->json('data.token');
    }

    // ---- Gateway ----

    public function test_login_returns_a_sanctum_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'token_type', 'user']]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The email or password you entered is incorrect.');
    }

    public function test_requests_without_token_get_uniform_401(): void
    {
        $this->get('/api/listings') // no Accept header: gateway still answers in JSON
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Please log in first. Send your token as "Authorization: Bearer <token>".',
                'data' => null,
            ]);
    }

    public function test_validation_errors_use_uniform_format(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        $this->withToken($token)->postJson('/api/listings', ['course' => 'X1', 'price' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['course', 'price', 'condition', 'title', 'category'], 'data.errors');
    }

    public function test_unknown_item_returns_uniform_404(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        $this->withToken($token)->getJson('/api/listings/9999')
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested item was not found.');
    }

    // ---- Module A / B: listings ----

    public function test_get_listings_filters_by_course(): void
    {
        $this->makeListing(overrides: ['course_code' => 'ICT651']);
        $this->makeListing(overrides: ['course_code' => 'MAT183', 'title' => 'Calculus']);
        $token = $this->tokenFor(User::factory()->create());

        $this->withToken($token)->getJson('/api/listings?course=ict651')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course_code', 'ICT651')
            ->assertJsonPath('message', '1 listing matching "ict651".');
    }

    public function test_create_listing_with_isbn_only_fills_details_from_google_books(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
            'title' => 'RESTful Web APIs',
            'authors' => ['Leonard Richardson'],
            'publisher' => "O'Reilly Media",
            'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9781449358068']],
        ]]]])]);
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))->postJson('/api/listings', [
            'isbn' => '978-1-4493-5806-8',
            'course' => 'ict651',
            'category' => 'Textbook',
            'price' => 40,
            'condition' => 'Good',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'RESTful Web APIs')
            ->assertJsonPath('data.author', 'Leonard Richardson')
            ->assertJsonPath('data.isbn', '9781449358068')
            ->assertJsonPath('data.course_code', 'ICT651');

        $this->assertDatabaseHas('listings', ['user_id' => $user->id, 'publisher' => "O'Reilly Media"]);
    }

    public function test_update_with_sync_refreshes_from_google_books(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
            'title' => 'RESTful Web APIs: Services for a Changing World', 'authors' => ['Leonard Richardson'],
        ]]]])]);
        $listing = $this->makeListing(overrides: ['isbn' => '9781449358068', 'title' => 'old title']);

        $this->withToken($this->tokenFor($listing->user))
            ->putJson("/api/listings/{$listing->id}", ['price' => 30, 'sync' => true])
            ->assertOk()
            ->assertJsonPath('data.title', 'RESTful Web APIs: Services for a Changing World')
            ->assertJsonPath('data.price', 30);
    }

    public function test_show_adds_google_books_details_found_by_isbn(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['id' => 'abc123', 'volumeInfo' => [
            'title' => 'RESTful Web APIs', 'description' => '<p>Learn <b>REST</b> &amp; hypermedia.</p>',
            'pageCount' => 406, 'categories' => ['Computers'], 'language' => 'en', 'publishedDate' => '2013-09-12',
            'infoLink' => 'http://books.google.com/books?id=abc123',
        ]]]])]);
        $listing = $this->makeListing(overrides: ['isbn' => '978-1-4493-5806-8', 'title' => 'RESTful Web APIs']);

        $this->withToken($this->tokenFor($listing->user))->getJson("/api/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Listing with details from Google Books.')
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.book_info.description', 'Learn REST & hypermedia.')
            ->assertJsonPath('data.book_info.page_count', 406)
            ->assertJsonPath('data.book_info.categories', ['Computers'])
            ->assertJsonPath('data.book_info.info_link', 'https://books.google.com/books?id=abc123')
            ->assertJsonPath('data.book_info.source', 'Google Books');

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=isbn:9781449358068'));
    }

    public function test_show_without_isbn_ignores_unrelated_google_results(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['id' => 'x', 'volumeInfo' => [
            'title' => 'Bahasa Mandarin 2', 'description' => 'Another book.',
        ]]]])]);
        $listing = $this->makeListing(overrides: ['isbn' => null, 'title' => 'Bahasa Mandarin Pengurusan']);

        $this->withToken($this->tokenFor($listing->user))->getJson("/api/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Bahasa Mandarin Pengurusan')
            ->assertJsonPath('data.book_info', null)
            ->assertJsonPath('message', 'Listing found. Google Books has no extra details for this book.');

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'intitle:Bahasa Mandarin Pengurusan'));
    }

    public function test_show_still_returns_the_listing_when_google_books_times_out(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));
        $listing = $this->makeListing(overrides: ['isbn' => '9781449358068']);

        $this->withToken($this->tokenFor($listing->user))->getJson("/api/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.book_info', null);
    }

    public function test_other_users_cannot_update_or_delete_a_listing(): void
    {
        $listing = $this->makeListing();
        $other = $this->tokenFor(User::factory()->create());

        $this->withToken($other)->putJson("/api/listings/{$listing->id}", ['price' => 1])
            ->assertForbidden()->assertJsonPath('message', 'You can only manage your own listings.');
        $this->withToken($other)->deleteJson("/api/listings/{$listing->id}")->assertForbidden();
        $this->assertNotNull(Listing::find($listing->id));
    }

    public function test_seller_can_delete_own_listing(): void
    {
        $listing = $this->makeListing();

        $this->withToken($this->tokenFor($listing->user))->deleteJson("/api/listings/{$listing->id}")
            ->assertOk()->assertJsonPath('message', 'Listing deleted.');
        $this->assertNull(Listing::find($listing->id));
    }

    // ---- Module C: orders + meetups (cash at the meetup) ----

    public function test_post_orders_books_a_listing(): void
    {
        $listing = $this->makeListing();

        $this->withToken($this->tokenFor(User::factory()->create()))
            ->postJson('/api/orders', ['listing_id' => $listing->id, 'note' => 'Blue shirt'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment', 'cash_on_meetup')
            ->assertJsonPath('data.meetup', null);

        $this->assertSame('reserved', $listing->fresh()->status);
    }

    public function test_post_orders_conflicts(): void
    {
        $order = $this->makeOrder();

        $this->withToken($this->tokenFor(User::factory()->create()))
            ->postJson('/api/orders', ['listing_id' => $order->listing_id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Sorry, this book has already been booked by someone else.');
    }

    public function test_seller_cannot_order_own_listing(): void
    {
        $listing = $this->makeListing();

        $this->withToken($this->tokenFor($listing->user))
            ->postJson('/api/orders', ['listing_id' => $listing->id])
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot book your own listing.');
    }

    public function test_post_meetups_geocodes_place_and_picks_nearest_point(): void
    {
        $order = $this->makeOrder();
        $mosque = config('unibook.meetup_points.mosque');
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => (string) $mosque['lat'], 'lon' => (string) $mosque['lng'], 'display_name' => 'Masjid An-Nur UiTM Perlis'],
            ]),
            'router.project-osrm.org/*' => Http::response([], 500), // OSRM down: straight-line fallback
        ]);

        $this->withToken($this->tokenFor($order->buyer))->postJson('/api/meetups', [
            'order_id' => $order->id,
            'meetup_time' => now()->addDay()->toIso8601String(),
            'place' => 'Masjid UiTM Arau',
        ])
            ->assertCreated()
            ->assertJsonPath('data.point', 'mosque')
            ->assertJsonPath('data.from.place', 'Masjid An-Nur UiTM Perlis')
            ->assertJsonPath('data.distance_m', 0);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'nominatim') && $r['q'] === 'Masjid UiTM Arau');
    }

    public function test_post_meetups_again_changes_the_meetup(): void
    {
        $order = $this->makeOrder(withMeetup: true);
        Http::fake();

        $this->withToken($this->tokenFor($order->seller))->postJson('/api/meetups', [
            'order_id' => $order->id,
            'meetup_time' => now()->addDays(2)->toIso8601String(),
            'meetup_point' => 'anjung_siswa',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Anjung Siswa')
            ->assertJsonPath('message', 'Meetup changed to Anjung Siswa.');

        $this->assertSame(1, $order->meetup()->count());
    }

    public function test_post_meetups_errors(): void
    {
        $order = $this->makeOrder();
        $token = $this->tokenFor($order->buyer);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->withToken($token)->postJson('/api/meetups', ['order_id' => $order->id, 'meetup_time' => now()->addDay()->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Choose a meetup point, or share your location so we can suggest the nearest one.');

        $this->withToken($token)->postJson('/api/meetups', ['order_id' => $order->id, 'meetup_time' => now()->addDay()->toIso8601String(), 'place' => 'Atlantis'])
            ->assertNotFound();
    }

    public function test_get_meetup_route_uses_osrm(): void
    {
        $order = $this->makeOrder(withMeetup: true);
        Http::fake(['router.project-osrm.org/route/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['distance' => 1200, 'duration' => 180, 'geometry' => ['coordinates' => [[100.27, 6.44], [100.2779, 6.4495]]]]],
        ])]);

        $this->withToken($this->tokenFor($order->buyer))->getJson("/api/meetups/{$order->meetup->id}/route")
            ->assertOk()
            ->assertJsonPath('data.method', 'road')
            ->assertJsonPath('data.distance_m', 1200)
            ->assertJsonPath('data.geometry.0', [6.44, 100.27]);

        $this->assertSame(1200, $order->meetup->fresh()->distance_m);
    }

    public function test_get_meetup_route_is_private(): void
    {
        $order = $this->makeOrder(withMeetup: true);

        $this->withToken($this->tokenFor(User::factory()->create()))->getJson("/api/meetups/{$order->meetup->id}/route")
            ->assertForbidden();
    }

    public function test_put_orders_seller_marks_completed(): void
    {
        $order = $this->makeOrder();

        $this->withToken($this->tokenFor($order->seller))->putJson("/api/orders/{$order->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('message', 'Order marked as completed. The buyer can now rate you.');
        $this->assertSame('sold', $order->listing->fresh()->status);
    }

    public function test_put_orders_buyer_rates_completed_order(): void
    {
        $order = $this->makeOrder(status: 'completed');

        $this->withToken($this->tokenFor($order->buyer))->putJson("/api/orders/{$order->id}", ['rating' => 4, 'review' => 'Good'])
            ->assertOk()
            ->assertJsonPath('data.rating', 4);
    }

    public function test_put_orders_buyer_cannot_complete(): void
    {
        $order = $this->makeOrder();

        $this->withToken($this->tokenFor($order->buyer))->putJson("/api/orders/{$order->id}", ['status' => 'completed'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the seller can mark the order as completed.');
    }

    public function test_put_orders_cancel_and_validation(): void
    {
        $order = $this->makeOrder();
        $token = $this->tokenFor($order->buyer);

        $this->withToken($token)->putJson("/api/orders/{$order->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'], 'data.errors');

        $this->withToken($token)->putJson("/api/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('available', $order->listing->fresh()->status);
    }

    public function test_strangers_cannot_see_an_order(): void
    {
        $order = $this->makeOrder();

        $this->withToken($this->tokenFor(User::factory()->create()))->getJson("/api/orders/{$order->id}")
            ->assertForbidden()->assertJsonPath('success', false);
    }
}
