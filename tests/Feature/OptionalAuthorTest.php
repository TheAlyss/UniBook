<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Author is optional: UiTM modules and local books often have no author printed. */
class OptionalAuthorTest extends TestCase
{
    use RefreshDatabase;

    private const MODULE = [
        'title' => 'Modul Pengaturcaraan Web',
        'category' => 'Textbook',
        'course' => 'ICT651',
        'price' => '15',
        'condition' => 'Good',
        'book_source' => 'manual',
    ];

    public function test_website_listing_can_be_posted_without_an_author(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/sell', self::MODULE + ['author' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $listing = Listing::firstOrFail();
        $this->assertNull($listing->author);
        $this->assertDatabaseHas('books', ['title' => 'Modul Pengaturcaraan Web', 'author' => null, 'source' => 'manual']);

        // Pages still show the book without an empty author line.
        $this->get('/')->assertOk()->assertSee('Modul Pengaturcaraan Web');
        $this->get(route('books.show', $listing))->assertOk()->assertSee('Modul Pengaturcaraan Web');
    }

    public function test_api_listing_can_be_created_without_an_author(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/listings', self::MODULE)
            ->assertCreated()
            ->assertJsonPath('data.author', null);
    }

    public function test_title_is_still_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/sell', array_diff_key(self::MODULE, ['title' => 1]))
            ->assertSessionHasErrors('title')
            ->assertSessionDoesntHaveErrors('author');
    }
}
