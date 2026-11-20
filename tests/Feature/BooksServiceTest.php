<?php

namespace Tests\Feature;

use App\Services\BooksService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests: BooksService against faked Google Books / Open Library responses.
 */
class BooksServiceTest extends TestCase
{
    private const GOOGLE = 'www.googleapis.com/books/v1/volumes*';
    private const OPEN_LIBRARY = 'openlibrary.org/search.json*';

    private function googleVolume(string $title, string $isbn13): array
    {
        return ['volumeInfo' => [
            'title' => $title,
            'authors' => ['Some Author'],
            'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => $isbn13]],
        ]];
    }

    public function test_isbn_search_uses_google_books_with_isbn_prefix(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->googleVolume('RESTful Web APIs', '9781449358068')]]),
            self::OPEN_LIBRARY => Http::response(['docs' => []]),
        ]);

        $results = app(BooksService::class)->search('978-1-4493-5806-8');

        $this->assertCount(1, $results);
        $this->assertSame('RESTful Web APIs', $results[0]['title']);
        $this->assertSame('Google Books', $results[0]['source']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'googleapis.com')
            && $r['q'] === 'isbn:9781449358068');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'openlibrary.org'));
    }

    public function test_title_search_returns_at_most_six_matches(): void
    {
        $items = [];
        for ($i = 1; $i <= 8; $i++) {
            $items[] = $this->googleVolume("Book {$i}", '978000000000' . $i);
        }
        Http::fake([self::GOOGLE => Http::response(['items' => $items])]);

        $results = app(BooksService::class)->search('web services');

        $this->assertCount(6, $results);
        $this->assertSame('Book 6', $results[5]['title']);
        Http::assertSent(fn (Request $r) => $r['q'] === 'web services' && (int) $r['maxResults'] === 6);
    }

    public function test_open_library_fallback_also_asks_for_six(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['totalItems' => 0]),
            self::OPEN_LIBRARY => Http::response(['docs' => [['title' => 'Calculus']]]),
        ]);

        app(BooksService::class)->search('Calculus');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'openlibrary.org') && (int) $r['limit'] === 6);
    }

    public function test_falls_back_to_open_library_when_google_returns_nothing(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['totalItems' => 0]),
            self::OPEN_LIBRARY => Http::response(['docs' => [['title' => 'Calculus', 'author_name' => ['James Stewart']]]]),
        ]);

        $results = app(BooksService::class)->search('Calculus Stewart');

        $this->assertSame('Open Library', $results[0]['source']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'openlibrary.org')
            && $r->hasHeader('User-Agent'));
    }

    public function test_falls_back_to_open_library_when_google_quota_is_exceeded(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['error' => ['code' => 429]], 429),
            self::OPEN_LIBRARY => Http::response(['docs' => [['title' => 'RESTful Web APIs', 'isbn' => ['9781449358068']]]]),
        ]);

        $results = app(BooksService::class)->search('9781449358068');

        $this->assertSame('Open Library', $results[0]['source']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'openlibrary.org') && $r['isbn'] === '9781449358068');
    }

    public function test_returns_empty_array_when_both_apis_time_out(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->assertSame([], app(BooksService::class)->search('Modul Pengaturcaraan Web'));
    }

    public function test_successful_results_are_cached(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['items' => [$this->googleVolume('RESTful Web APIs', '9781449358068')]]),
        ]);

        $service = app(BooksService::class);
        $service->search('9781449358068');
        $service->search('978-1449358068');

        Http::assertSentCount(1);
    }

    public function test_empty_results_are_not_cached(): void
    {
        Http::fake([
            self::GOOGLE => Http::response(['totalItems' => 0]),
            self::OPEN_LIBRARY => Http::response(['docs' => []]),
        ]);

        $service = app(BooksService::class);
        $service->search('unknown local module');
        $service->search('unknown local module');

        Http::assertSentCount(4);
    }
}
