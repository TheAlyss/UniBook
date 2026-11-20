<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\BookLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Lookup order: books table -> Google Books -> Open Library -> manual,
 * relevance check (Part 3) and cover order (Part 5).
 */
class BookLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE = 'www.googleapis.com/books/v1/volumes*';
    private const OPEN_LIBRARY = 'openlibrary.org/search.json*';
    private const OL_COVERS = 'covers.openlibrary.org/*';

    private function volume(string $title, ?string $isbn = null, ?string $thumbnail = null, array $authors = ['Some Author']): array
    {
        return ['volumeInfo' => array_filter([
            'title' => $title,
            'authors' => $authors,
            'industryIdentifiers' => $isbn ? [['type' => 'ISBN_13', 'identifier' => $isbn]] : null,
            'imageLinks' => $thumbnail ? ['thumbnail' => $thumbnail] : null,
        ])];
    }

    public function test_catalogue_is_searched_first(): void
    {
        Http::fake();
        Book::create(['title' => 'Bahasa Mandarin untuk Pengurusan', 'author' => 'Lim Swee Hong', 'source' => 'manual']);

        $found = app(BookLookupService::class)->lookup('Bahasa Mandarin Pengurusan');

        $this->assertSame('catalog', $found['source']);
        $this->assertSame('UniBook catalogue', $found['matches'][0]['source']);
    }

    public function test_title_search_shows_catalogue_first_then_google_without_duplicates(): void
    {
        Book::create(['title' => 'Harry Potter and the Chamber of Secrets', 'author' => 'J.K. Rowling', 'isbn' => '9781781100509', 'source' => 'google_books']);
        Http::fake([
            self::GOOGLE => Http::response(['items' => [
                $this->volume('Harry Potter and the Chamber of Secrets', '9781781100509', null, ['J.K. Rowling']), // already in catalogue
                $this->volume('Harry Potter and the Goblet of Fire', '9781781100219', null, ['J.K. Rowling']),
                $this->volume('Harry Potter and the Prisoner of Azkaban', '9781781100516', null, ['J.K. Rowling']),
            ]]),
            self::OL_COVERS => Http::response('', 404),
        ]);

        $found = app(BookLookupService::class)->lookup('harry potter');

        $this->assertSame([
            ['Harry Potter and the Chamber of Secrets', 'UniBook catalogue'],
            ['Harry Potter and the Goblet of Fire', 'Google Books'],
            ['Harry Potter and the Prisoner of Azkaban', 'Google Books'],
        ], array_map(fn ($b) => [$b['title'], $b['source']], $found['matches']));
    }

    public function test_isbn_found_in_catalogue_does_not_call_google(): void
    {
        Http::fake();
        Book::create(['title' => 'Clean Code', 'author' => 'Robert C. Martin', 'isbn' => '9780132350884', 'source' => 'google_books']);

        $found = app(BookLookupService::class)->lookup('978-0-13-235088-4');

        $this->assertSame('catalog', $found['source']);
        $this->assertCount(1, $found['matches']);
        Http::assertNothingSent();
    }

    public function test_unrelated_google_results_are_hidden_as_others(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [
                $this->volume('Pengurusan Kewangan Peribadi'),
                $this->volume('Mandarin Chinese for Beginners'),
                $this->volume('Principles of Management'),
            ]]),
            self::OPEN_LIBRARY => Http::response(['docs' => [['title' => 'Bahasa Melayu Tingkatan 4']]]),
        ]);

        $found = app(BookLookupService::class)->lookup('Bahasa Mandarin Pengurusan');

        $this->assertNull($found['source']);
        $this->assertSame([], $found['matches']);
        $this->assertCount(4, $found['others']);
    }

    public function test_google_match_wins_and_open_library_is_not_called(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [
                $this->volume('Principles of Management'),
                $this->volume('Database Systems: Design, Implementation, and Management', '9781337627900', 'http://books.google.com/x?id=1&edge=curl', ['Carlos Coronel']),
            ]]),
        ]);

        $found = app(BookLookupService::class)->lookup('Database Systems Coronel');

        $this->assertSame('google_books', $found['source']);
        $this->assertCount(1, $found['matches']);
        $this->assertSame('https://books.google.com/x?id=1', $found['matches'][0]['cover_url']);
        $this->assertSame('google_books', $found['matches'][0]['cover_source']);
        $this->assertCount(1, $found['others']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'openlibrary.org'));
    }

    public function test_open_library_is_tried_when_google_has_no_real_match(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->volume('Unrelated Cookbook')]]),
            self::OPEN_LIBRARY => Http::response(['docs' => [['title' => 'Interlok', 'author_name' => ['Abdullah Hussain']]]]),
        ]);

        $found = app(BookLookupService::class)->lookup('Interlok Abdullah Hussain');

        $this->assertSame('open_library', $found['source']);
        $this->assertSame('Interlok', $found['matches'][0]['title']);
    }

    public function test_cover_falls_back_to_open_library_by_isbn_with_default_false(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->volume('Clean Code', '9780132350884', null, ['Robert C. Martin'])]]),
            self::OL_COVERS => Http::response('', 200),
        ]);

        $match = app(BookLookupService::class)->lookup('9780132350884')['matches'][0];

        $this->assertSame('https://covers.openlibrary.org/b/isbn/9780132350884-M.jpg?default=false', $match['cover_url']);
        $this->assertSame('open_library', $match['cover_source']);
        Http::assertSent(fn (Request $r) => $r->method() === 'HEAD' && str_contains($r->url(), 'default=false'));
    }

    public function test_open_library_404_cover_means_no_cover(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->volume('Clean Code', '9780132350884')]]),
            self::OL_COVERS => Http::response('', 404),
        ]);

        $match = app(BookLookupService::class)->lookup('9780132350884')['matches'][0];

        $this->assertNull($match['cover_url']);
        $this->assertNull($match['cover_source']);
    }

    public function test_isbn_results_match_on_the_isbn_not_the_title(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->volume('A Title With No Shared Words', '9780132350884', 'https://img')]]),
        ]);

        $found = app(BookLookupService::class)->lookup('978-0-13-235088-4');

        $this->assertSame('9780132350884', $found['isbn']);
        $this->assertCount(1, $found['matches']);
    }

    public function test_isbn_search_rejects_a_book_with_a_different_isbn(): void
    {
        // Real case: Google returned "The Elements of Technical Writing" for the ISBN of "The Elements of Style".
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->volume('The Elements of Technical Writing', null, 'https://img')]]),
            self::OPEN_LIBRARY => Http::response(['docs' => [['title' => 'The Elements of Style', 'isbn' => ['0205309023', '9780205309023']]]]),
            self::OL_COVERS => Http::response('', 404),
        ]);

        $found = app(BookLookupService::class)->lookup('9780205309023');

        $this->assertSame('open_library', $found['source']);
        $this->assertSame('The Elements of Style', $found['matches'][0]['title']);
        $this->assertSame('The Elements of Technical Writing', $found['others'][0]['title']);
    }

    public function test_isbn_10_and_13_are_the_same_book(): void
    {
        $this->assertSame('9780205309023', BookLookupService::isbn13('0205309023'));
        $this->assertSame('9781449358068', BookLookupService::isbn13('1449358063'));
        $this->assertSame('9781449358068', BookLookupService::isbn13('9781449358068'));
    }

    public function test_listing_with_manual_details_is_saved_in_catalogue_and_found_next_time(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'Bahasa Mandarin untuk Pengurusan', 'author' => 'Lim Swee Hong', 'edition' => 'Level 1',
            'category' => 'Textbook', 'price' => 20, 'condition' => 'Good', 'book_source' => 'manual',
        ])->assertSessionHasNoErrors();

        $book = Book::firstOrFail();
        $this->assertSame('manual', $book->source);
        $this->assertSame($book->id, $user->listings()->first()->book_id);

        $this->getJson(route('listings.lookup', ['q' => 'Bahasa Mandarin Pengurusan']))
            ->assertOk()
            ->assertJsonPath('source', 'catalog')
            ->assertJsonPath('results.0.edition', 'Level 1');
    }

    public function test_remember_never_overwrites_existing_catalogue_details(): void
    {
        $book = Book::create(['isbn' => '9780132350884', 'title' => 'Clean Code', 'author' => 'Robert C. Martin', 'source' => 'google_books']);

        app(BookLookupService::class)->remember(['isbn' => '9780132350884', 'title' => 'clean code (typo)', 'publisher' => 'Prentice Hall'], 'manual');

        $book->refresh();
        $this->assertSame('Clean Code', $book->title);
        $this->assertSame('Prentice Hall', $book->publisher);
        $this->assertSame('google_books', $book->source);
        $this->assertSame(1, Book::count());
    }

    public function test_web_lookup_returns_others_with_404_when_nothing_matches(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->volume('Principles of Management')]]),
            self::OPEN_LIBRARY => Http::response(['docs' => []]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('listings.lookup', ['q' => 'Bahasa Mandarin Pengurusan']))
            ->assertNotFound()
            ->assertJsonPath('results', [])
            ->assertJsonPath('other_results.0.title', 'Principles of Management');
    }
}
