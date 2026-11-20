<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only class in UniBook that talks to Google Books and Open Library.
 *
 * Every result is mapped to the UniBook book schema:
 *   {title, author, edition, publisher, published_year, isbn, cover_url, cover_source, source, source_key}
 *
 * google() and openLibrary() can be called separately (BookLookupService checks relevance between
 * them). Each is cached for a day; only non-empty results are cached so failures are retried.
 * search() keeps the simple "Google, else Open Library" behaviour.
 */
class BooksService
{
    /** How many results to ask each API for (Google Books "maxResults" / Open Library "limit"). */
    public const MAX_RESULTS = 6;

    /** Photo scans fetch more candidates, because the AI ranks them afterwards. */
    public const SCAN_RESULTS = 10;

    private const GOOGLE_URL = 'https://www.googleapis.com/books/v1/volumes';
    private const OPEN_LIBRARY_URL = 'https://openlibrary.org/search.json';
    private const OPEN_LIBRARY_COVER_ID = 'https://covers.openlibrary.org/b/id/%d-M.jpg';
    /** "?default=false" makes Open Library answer 404 instead of a blank image when it has no cover. */
    private const OPEN_LIBRARY_COVER_ISBN = 'https://covers.openlibrary.org/b/isbn/%s-M.jpg?default=false';

    /** Google Books, else Open Library. */
    public function search(string $query): array
    {
        return $this->google($query) ?: $this->openLibrary($query);
    }

    /** @return array<int, array<string, ?string>> */
    public function google(string $query, int $limit = self::MAX_RESULTS): array
    {
        return $this->cached('google', $query, $limit, fn ($q, $isbn) => $this->fetchGoogle($q, $isbn, $limit));
    }

    /** @return array<int, array<string, ?string>> */
    public function openLibrary(string $query, int $limit = self::MAX_RESULTS): array
    {
        return $this->cached('openlibrary', $query, $limit, fn ($q, $isbn) => $this->fetchOpenLibrary($q, $isbn, $limit));
    }

    /** Remove cached lookups (used when a listing is deleted or re-synced). */
    public function forget(string $query): void
    {
        foreach (['google', 'openlibrary'] as $source) {
            foreach ([self::MAX_RESULTS, self::SCAN_RESULTS] as $limit) {
                Cache::forget(self::cacheKey($source, $query, $limit));
            }
        }
    }

    /** First match for an ISBN, or null. */
    public function findByIsbn(string $isbn): ?array
    {
        return self::normaliseIsbn($isbn) ? ($this->search($isbn)[0] ?? null) : null;
    }

    /**
     * Part 5: Open Library cover by ISBN, checked with ?default=false (404 = no cover).
     * Several ISBNs are checked in parallel. Results (including "no cover") are cached for a week.
     *
     * @param list<string> $isbns
     * @return array<string, ?string> isbn => cover URL or null
     */
    public function openLibraryCovers(array $isbns): array
    {
        $isbns = array_values(array_unique(array_filter($isbns)));
        $found = [];
        $toCheck = [];

        foreach ($isbns as $isbn) {
            $key = "ol-cover:{$isbn}";
            if (Cache::has($key)) {
                $found[$isbn] = Cache::get($key) ?: null;
            } else {
                $toCheck[] = $isbn;
            }
        }

        if ($toCheck !== []) {
            try {
                $responses = Http::pool(fn (Pool $pool) => array_map(
                    fn ($isbn) => $pool->as($isbn)->timeout(6)
                        ->withHeaders(['User-Agent' => config('services.open_library.user_agent')])
                        ->head(sprintf(self::OPEN_LIBRARY_COVER_ISBN, $isbn)),
                    $toCheck
                ));
            } catch (\Throwable $e) {
                Log::warning('Open Library covers unreachable', ['error' => $e->getMessage()]);
                $responses = [];
            }

            foreach ($toCheck as $isbn) {
                $response = $responses[$isbn] ?? null;
                if (! $response instanceof \Illuminate\Http\Client\Response) {
                    $found[$isbn] = null; // network problem: don't cache, try again next time
                    continue;
                }
                $url = $response->successful() ? sprintf(self::OPEN_LIBRARY_COVER_ISBN, $isbn) : null;
                Cache::put("ol-cover:{$isbn}", $url ?? '', now()->addWeek());
                $found[$isbn] = $url;
            }
        }

        return $found;
    }

    /**
     * Fuller details for ONE book the seller picked. Search results often lack the publisher and never
     * carry an edition, so we ask the APIs again:
     *   - Google Books volume by ID (publisher, published date)
     *   - Open Library edition by ISBN (edition_name, publishers)
     *   - otherwise an edition written in the title itself, e.g. "... (2nd Edition)"
     * Only real API data is returned (cached for a week); fields that are still unknown stay null.
     *
     * @return array{author: ?string, edition: ?string, publisher: ?string, published_year: ?string}
     */
    public function details(?string $googleId, ?string $isbn, ?string $title = null): array
    {
        $isbn = $isbn ? self::normaliseIsbn($isbn) : null;
        $googleId = $googleId && preg_match('/^[A-Za-z0-9_-]{4,40}$/', $googleId) ? $googleId : null;

        $key = 'books:details:' . md5("{$googleId}|{$isbn}|{$title}");
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $found = (function () use ($googleId, $isbn, $title) {
            $found = ['author' => null, 'edition' => null, 'publisher' => null, 'published_year' => null];

            if ($googleId) {
                $info = $this->getJson('https://www.googleapis.com/books/v1/volumes/' . $googleId, array_filter([
                    'key' => config('services.google_books.key'),
                ]))['volumeInfo'] ?? [];
                $found['author'] = implode(', ', $info['authors'] ?? []) ?: null;
                $found['publisher'] = $info['publisher'] ?? null;
                $found['published_year'] = isset($info['publishedDate']) ? substr($info['publishedDate'], 0, 4) : null;
                $title = $title ?: trim(($info['title'] ?? '') . ' ' . ($info['subtitle'] ?? ''));
            }

            if ($isbn) {
                $edition = $this->getJson("https://openlibrary.org/isbn/{$isbn}.json", [], true);
                $found['edition'] = isset($edition['edition_name']) ? trim($edition['edition_name']) : null;
                $found['publisher'] ??= $edition['publishers'][0] ?? null;
                $found['published_year'] ??= isset($edition['publish_date']) && preg_match('/\d{4}/', $edition['publish_date'], $y) ? $y[0] : null;
            }

            $found['edition'] = $found['edition'] ?: self::editionFromTitle((string) $title);

            return $found;
        })();

        if (array_filter($found)) { // nothing found (or network down): try again next time
            Cache::put($key, $found, now()->addWeek());
        }

        return $found;
    }

    /** "Database Systems (13th Edition)" -> "13th Edition"; "Second Edition" -> "Second Edition". */
    public static function editionFromTitle(string $title): ?string
    {
        $ordinal = '\d{1,2}(?:st|nd|rd|th)|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|revised|international|global';

        return preg_match("/\b({$ordinal})\s+(edition|ed\.)/i", $title, $m)
            ? ucfirst($m[1]) . ' Edition'
            : null;
    }

    private function getJson(string $url, array $params = [], bool $openLibrary = false): array
    {
        try {
            // Short timeout: the seller is waiting for these extra fields.
            $request = Http::timeout(5)->acceptJson();
            if ($openLibrary) {
                $request = $request->withHeaders(['User-Agent' => config('services.open_library.user_agent')]);
            }
            $response = $request->get($url, $params);
        } catch (ConnectionException $e) {
            Log::warning('Book details unreachable', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }

        return $response->successful() ? (array) $response->json() : [];
    }

    /** Returns the ISBN digits if the query looks like an ISBN-10 or ISBN-13, otherwise null. */
    public static function normaliseIsbn(string $query): ?string
    {
        $digits = strtoupper(preg_replace('/[\s-]/', '', $query));

        return preg_match('/^(97[89]\d{10}|\d{9}[\dX])$/', $digits) ? $digits : null;
    }

    /** Google Books volume JSON -> UniBook book schema. */
    public static function mapGoogleVolume(array $item): array
    {
        $info = $item['volumeInfo'] ?? [];

        $identifiers = collect($info['industryIdentifiers'] ?? [])->pluck('identifier', 'type');
        // Part 5: use imageLinks.thumbnail when Google has it.
        $cover = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;

        return [
            'title' => trim(($info['title'] ?? '') . (isset($info['subtitle']) ? ': ' . $info['subtitle'] : '')),
            'author' => implode(', ', $info['authors'] ?? []) ?: null,
            'edition' => null,
            'publisher' => $info['publisher'] ?? null,
            'published_year' => isset($info['publishedDate']) ? substr($info['publishedDate'], 0, 4) : null,
            'isbn' => $identifiers['ISBN_13'] ?? $identifiers['ISBN_10'] ?? null,
            // Google returns http:// thumbnails; force https so browsers don't block them.
            'cover_url' => $cover ? str_replace(['http://', '&edge=curl'], ['https://', ''], $cover) : null,
            'cover_source' => $cover ? 'google_books' : null,
            'source' => 'Google Books',
            'source_key' => 'google_books',
            'google_id' => $item['id'] ?? null,
        ];
    }

    /** Open Library search doc JSON -> UniBook book schema. */
    public static function mapOpenLibraryDoc(array $doc, ?string $searchedIsbn = null): array
    {
        $isbns = $doc['isbn'] ?? [];
        $isbn = ($searchedIsbn && in_array($searchedIsbn, $isbns, true))
            ? $searchedIsbn
            : (collect($isbns)->first(fn ($i) => strlen($i) === 13) ?? ($isbns[0] ?? null));

        return [
            'title' => trim(($doc['title'] ?? '') . (isset($doc['subtitle']) ? ': ' . $doc['subtitle'] : '')),
            'author' => implode(', ', array_slice($doc['author_name'] ?? [], 0, 3)) ?: null,
            'edition' => null,
            'publisher' => $doc['publisher'][0] ?? null,
            'published_year' => isset($doc['first_publish_year']) ? (string) $doc['first_publish_year'] : null,
            'isbn' => $isbn,
            'cover_url' => isset($doc['cover_i']) ? sprintf(self::OPEN_LIBRARY_COVER_ID, $doc['cover_i']) : null,
            'cover_source' => isset($doc['cover_i']) ? 'open_library' : null,
            'source' => 'Open Library',
            'source_key' => 'open_library',
        ];
    }

    private function cached(string $source, string $query, int $limit, callable $fetch): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $key = self::cacheKey($source, $query, $limit);
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $results = $fetch($query, self::normaliseIsbn($query));
        if ($results !== []) {
            Cache::put($key, $results, now()->addDay());
        }

        return $results;
    }

    /** The limit and source are part of the key, so changes never serve old lists. */
    private static function cacheKey(string $source, string $query, int $limit = self::MAX_RESULTS): string
    {
        $isbn = self::normaliseIsbn($query);

        return "books:{$source}:{$limit}:" . md5($isbn ? "isbn:{$isbn}" : mb_strtolower(trim($query)));
    }

    private function fetchGoogle(string $query, ?string $isbn, int $limit): array
    {
        $params = array_filter([
            'q' => $isbn ? "isbn:{$isbn}" : $query,
            'maxResults' => $limit,
            'printType' => 'books',
            'key' => config('services.google_books.key'),
        ]);

        try {
            $response = Http::timeout(8)->acceptJson()->get(self::GOOGLE_URL, $params);
        } catch (ConnectionException $e) {
            Log::warning('Google Books unreachable', ['error' => $e->getMessage()]);
            return [];
        }

        if ($response->failed()) {
            Log::warning('Google Books request failed', ['status' => $response->status()]);
            return [];
        }

        return collect($response->json('items', []))
            ->take($limit)
            ->map(fn (array $item) => self::mapGoogleVolume($item))
            ->filter(fn (array $book) => $book['title'] !== '')
            ->values()
            ->all();
    }

    private function fetchOpenLibrary(string $query, ?string $isbn, int $limit): array
    {
        $params = [
            $isbn ? 'isbn' : 'q' => $isbn ?? $query,
            'limit' => $limit,
            'fields' => 'title,subtitle,author_name,publisher,first_publish_year,isbn,cover_i',
        ];

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => config('services.open_library.user_agent')])
                ->acceptJson()
                ->get(self::OPEN_LIBRARY_URL, $params);
        } catch (ConnectionException $e) {
            Log::warning('Open Library unreachable', ['error' => $e->getMessage()]);
            return [];
        }

        if ($response->failed()) {
            Log::warning('Open Library request failed', ['status' => $response->status()]);
            return [];
        }

        return collect($response->json('docs', []))
            ->take($limit)
            ->map(fn (array $doc) => self::mapOpenLibraryDoc($doc, $isbn))
            ->filter(fn (array $book) => $book['title'] !== '')
            ->values()
            ->all();
    }
}
