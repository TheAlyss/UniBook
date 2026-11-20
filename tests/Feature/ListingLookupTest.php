<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ListingLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_use_the_lookup(): void
    {
        $this->getJson('/listings/lookup?q=9781449358068')->assertUnauthorized();
    }

    public function test_lookup_returns_matches_as_json(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(['items' => [['volumeInfo' => ['title' => 'RESTful Web APIs']]]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/listings/lookup?q=RESTful')
            ->assertOk()
            ->assertJsonPath('results.0.title', 'RESTful Web APIs')
            ->assertJsonPath('message', '1 match found. Choose the correct book.');
    }

    public function test_lookup_returns_404_when_nothing_is_found(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(['totalItems' => 0]),
            'openlibrary.org/*' => Http::response(['docs' => []]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/listings/lookup?q=Modul UiTM')
            ->assertNotFound()
            ->assertJsonPath('message', 'No matching book found. Please fill in the details manually.');
    }

    public function test_lookup_validates_the_query(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/listings/lookup?q=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q' => 'Please enter at least 2 characters.']);
    }

    public function test_listing_saves_isbn_and_cover_from_lookup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/sell', [
            'title' => 'RESTful Web APIs',
            'author' => 'Leonard Richardson',
            'course' => 'ICT651',
            'category' => 'Textbook',
            'price' => 40,
            'condition' => 'Good',
            'isbn' => '9781449358068',
            'cover_url' => 'https://covers.openlibrary.org/b/id/8514661-M.jpg',
        ])->assertRedirect();

        $this->assertDatabaseHas('listings', [
            'user_id' => $user->id,
            'isbn' => '9781449358068',
            'cover_url' => 'https://covers.openlibrary.org/b/id/8514661-M.jpg',
        ]);
    }

    public function test_cover_url_must_be_https(): void
    {
        $this->actingAs(User::factory()->create())->post('/sell', [
            'title' => 'X', 'author' => 'Y', 'course' => 'ICT651', 'category' => 'Textbook', 'price' => 10, 'condition' => 'Good',
            'cover_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('cover_url');
    }
}
