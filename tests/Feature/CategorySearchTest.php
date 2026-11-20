<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesListings;
use Tests\TestCase;

/**
 * Part 2: category (required), course code (optional), one search box + category filter.
 */
class CategorySearchTest extends TestCase
{
    use CreatesListings, RefreshDatabase;

    private function listingForm(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'The Alchemist',
            'author' => 'Paulo Coelho',
            'category' => 'Novel',
            'price' => 15,
            'condition' => 'Good',
        ];
    }

    public function test_course_code_is_optional_and_category_is_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('listings.store'), $this->listingForm())
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('listings', ['title' => 'The Alchemist', 'category' => 'Novel', 'course_code' => null]);
    }

    public function test_empty_course_code_is_stored_as_null(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->listingForm(['course' => '  ']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('listings', ['title' => 'The Alchemist', 'course_code' => null]);
    }

    public function test_category_is_required_and_must_be_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('listings.store'), $this->listingForm(['category' => '']))
            ->assertSessionHasErrors(['category' => 'Please choose a category.']);

        $this->post(route('listings.store'), $this->listingForm(['category' => 'Comics']))
            ->assertSessionHasErrors('category');
    }

    public function test_invalid_course_code_is_still_rejected_when_given(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->listingForm(['course' => 'ABC']))
            ->assertSessionHasErrors(['course' => 'The course code must be 3 letters followed by 3 digits, for example ICT651.']);
    }

    public function test_home_search_matches_title_author_isbn_and_course_code(): void
    {
        $this->makeListing(overrides: ['title' => 'RESTful Web APIs', 'author' => 'Leonard Richardson', 'isbn' => '9781449358068', 'course_code' => 'ICT651', 'category' => 'Textbook']);
        $this->makeListing(overrides: ['title' => 'The Alchemist', 'author' => 'Paulo Coelho', 'course_code' => null, 'category' => 'Novel']);

        $this->get(route('home', ['q' => 'restful']))->assertSee('RESTful Web APIs')->assertDontSee('The Alchemist');
        $this->get(route('home', ['q' => 'coelho']))->assertSee('The Alchemist')->assertDontSee('RESTful Web APIs');
        $this->get(route('home', ['q' => '978-1-4493-5806-8']))->assertSee('RESTful Web APIs')->assertDontSee('The Alchemist');
        $this->get(route('home', ['q' => 'ict651']))->assertSee('RESTful Web APIs')->assertDontSee('The Alchemist');
    }

    public function test_home_category_filter_and_course_badge(): void
    {
        $this->makeListing(overrides: ['title' => 'RESTful Web APIs', 'course_code' => 'ICT651', 'category' => 'Textbook']);
        $this->makeListing(overrides: ['title' => 'The Alchemist', 'course_code' => null, 'category' => 'Novel']);

        $this->get(route('home', ['category' => 'Novel']))
            ->assertSee('The Alchemist')->assertDontSee('RESTful Web APIs')->assertSee('1 book');

        // Course code shown as a badge only for listings that have one.
        $this->get(route('home'))->assertSee('title="Course code">ICT651</span>', false);
        $this->get(route('home', ['category' => 'Novel']))->assertDontSee('title="Course code"', false);
    }

    public function test_home_can_sort_by_price_and_shows_empty_state(): void
    {
        $this->makeListing(overrides: ['title' => 'Middle Book', 'price' => 20]);
        $this->makeListing(overrides: ['title' => 'Cheap Book', 'price' => 5]);
        $this->makeListing(overrides: ['title' => 'Dear Book', 'price' => 90]);

        $this->get('/?sort=price_asc')->assertOk()->assertSeeInOrder(['Cheap Book', 'Middle Book', 'Dear Book']);
        $this->get('/?sort=price_desc')->assertOk()->assertSeeInOrder(['Dear Book', 'Middle Book', 'Cheap Book']);
        $this->get('/?sort=nonsense')->assertOk(); // unknown sort falls back to newest

        $this->get('/?q=zzzz')->assertOk()->assertSee('No books match your search');
    }

    public function test_unknown_category_in_url_is_ignored(): void
    {
        $this->makeListing(overrides: ['title' => 'The Alchemist', 'category' => 'Novel']);

        $this->get(route('home', ['category' => 'Comics']))->assertOk()->assertSee('The Alchemist');
    }

    public function test_api_search_and_category_filter(): void
    {
        $this->makeListing(overrides: ['title' => 'RESTful Web APIs', 'category' => 'Textbook']);
        $this->makeListing(overrides: ['title' => 'The Alchemist', 'author' => 'Paulo Coelho', 'course_code' => null, 'category' => 'Novel']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/listings?q=coelho&category=Novel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'Novel')
            ->assertJsonPath('data.0.course_code', null)
            ->assertJsonPath('message', '1 listing matching "coelho" in Novel.');

        $this->actingAs($user, 'sanctum')->getJson('/api/listings?category=Comics')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category'], 'data.errors');
    }
}
