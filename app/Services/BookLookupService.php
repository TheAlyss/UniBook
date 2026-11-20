<?php

namespace App\Services;

use App\Models\Book;
use App\Support\Covers;
use App\Support\TitleMatcher;

/**
 * Book lookup order (the heart of Module A):
 *   1. UniBook's own `books` table
 *   2. Google Books
 *   3. Open Library
 *   4. nothing found -> the seller enters the book manually (saved with source = manual)
 *
 * Title searches are checked for relevance (TitleMatcher). Results that don't really match the
 * search text are returned separately as "other results" so the UI can hide them.
 * ISBN searches are exact, so every result counts as a match.
 */
class BookLookupService
{
    public function __construct(private BooksService $books)
    {
    }

    /**
     * @return array{
     *   query: string, isbn: ?string, source: ?string,
     *   matches: list<array>, others: list<array>
     * } source = catalog | google_books | open_library | null (no match)
     */
    public function lookup(string $query): array
    {
        $query = trim($query);
        $isbn = BooksService::normaliseIsbn($query);
        $result = ['query' => $query, 'isbn' => $isbn, 'source' => null, 'matches' => [], 'others' => []];
        if ($query === '') {
            return $result;
        }

        // 1. Our own catalogue. An ISBN found here is exact, so we stop.
        $catalog = $this->searchCatalog($query, $isbn);
        if ($catalog !== [] && $isbn) {
            return ['source' => 'catalog', 'matches' => $catalog] + $result;
        }

        // 2. Google Books, then 3. Open Library. For a title search, catalogue matches are shown
        //    first and the online matches follow (the same book is not shown twice).
        $others = [];
        foreach (['google_books' => 'google', 'open_library' => 'openLibrary'] as $source => $method) {
            [$matches, $unrelated] = $this->split($query, $isbn, $this->books->$method($query));
            $others = array_merge($others, $unrelated);

            if ($matches !== []) {
                return [
                    'source' => $catalog !== [] ? 'catalog' : $source,
                    'matches' => array_merge($catalog, self::withoutDuplicates($this->withCovers($matches), $catalog)),
                    'others' => array_slice($others, 0, BooksService::MAX_RESULTS),
                ] + $result;
            }
        }

        if ($catalog !== []) {
            return ['source' => 'catalog', 'matches' => $catalog, 'others' => array_slice($others, 0, BooksService::MAX_RESULTS)] + $result;
        }

        // 4. No match: the seller enters it manually.
        return ['others' => array_slice($others, 0, BooksService::MAX_RESULTS)] + $result;
    }

    /** Online results minus the books already in our catalogue (same ISBN, or same title + author). */
    private static function withoutDuplicates(array $online, array $catalog): array
    {
        $key = fn (array $b) => mb_strtolower(trim($b['title']) . '|' . trim((string) ($b['author'] ?? '')));
        $isbns = [];
        $titles = [];
        foreach ($catalog as $b) {
            if ($b['isbn']) {
                $isbns[self::isbn13(BooksService::normaliseIsbn($b['isbn']) ?? $b['isbn'])] = true;
            }
            $titles[$key($b)] = true;
        }

        return array_values(array_filter($online, function (array $b) use ($isbns, $titles, $key) {
            $isbn = $b['isbn'] ? self::isbn13(BooksService::normaliseIsbn($b['isbn']) ?? $b['isbn']) : null;

            return ! ($isbn && isset($isbns[$isbn])) && ! isset($titles[$key($b)]);
        }));
    }

    /**
     * Candidates for the AI to rank after a photo scan, WITHOUT the relevance filter:
     * our catalogue's matches, then Google Books (up to 10), or Open Library if Google has nothing.
     * Duplicates (same ISBN) are removed. Covers follow the Part 5 order.
     *
     * @return array{source: ?string, books: list<array>}
     */
    public function candidates(string $query, int $limit = BooksService::SCAN_RESULTS): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['source' => null, 'books' => []];
        }

        $catalog = $this->searchCatalog($query, null);
        $online = $this->books->google($query, $limit);
        $source = $online !== [] ? 'google_books' : null;
        if ($online === []) {
            $online = $this->books->openLibrary($query, $limit);
            $source = $online !== [] ? 'open_library' : null;
        }

        $seen = [];
        $books = [];
        foreach (array_merge($catalog, $this->withCovers($online)) as $book) {
            $isbn = $book['isbn'] ? self::isbn13(BooksService::normaliseIsbn($book['isbn']) ?? $book['isbn']) : null;
            if ($isbn && isset($seen[$isbn])) {
                continue;
            }
            $seen[$isbn ?? uniqid()] = true;
            $books[] = $book;
        }

        return ['source' => $catalog !== [] ? 'catalog' : $source, 'books' => array_slice($books, 0, $limit)];
    }

    /**
     * The non-AI relevance filter (Part 3), used when the AI is not available.
     *
     * @return array{0: list<array>, 1: list<array>} [matches, others]
     */
    public function relevance(string $query, array $books): array
    {
        return $this->split($query, BooksService::normaliseIsbn($query), $books);
    }

    /**
     * Saves the book behind a listing in the catalogue so the next seller finds it at step 1.
     * $source: catalog (chosen from our table), google_books, open_library, or manual (typed / OCR).
     */
    public function remember(array $data, ?string $source = null, ?int $bookId = null): ?Book
    {
        if ($source === 'catalog' && $bookId && ($book = Book::find($bookId))) {
            return $book;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $source = in_array($source, Book::SOURCES, true) ? $source : 'manual';
        $isbn = isset($data['isbn']) ? BooksService::normaliseIsbn((string) $data['isbn']) : null;
        $author = trim((string) ($data['author'] ?? '')) ?: null;

        $book = $isbn
            ? Book::firstOrNew(['isbn' => $isbn])
            : Book::whereNull('isbn')
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                ->whereRaw('LOWER(COALESCE(author, \'\')) = ?', [mb_strtolower((string) $author)])
                ->firstOrNew();

        $details = [
            'title' => $title,
            'author' => $author,
            'edition' => $data['edition'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'published_year' => $data['published_year'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'cover_source' => $data['cover_source'] ?? null,
        ];

        if (! $book->exists) {
            $book->fill(['isbn' => $isbn, 'source' => $source] + $details)->save();

            return $book;
        }

        // Existing book: only fill in what is still missing, never overwrite.
        foreach ($details as $field => $value) {
            if (blank($book->$field) && filled($value)) {
                $book->$field = $value;
            }
        }
        $book->save();

        return $book;
    }

    /** @return list<array> */
    private function searchCatalog(string $query, ?string $isbn): array
    {
        if ($isbn) {
            $book = Book::where('isbn', $isbn)->first();

            return $book ? [$book->toLookupResult()] : [];
        }

        $words = TitleMatcher::words($query);
        if ($words === []) {
            return [];
        }

        return Book::query()
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('title', 'like', '%' . addcslashes($word, '%_\\') . '%')
                        ->orWhere('author', 'like', '%' . addcslashes($word, '%_\\') . '%');
                }
            })
            ->limit(50)
            ->get()
            ->filter(fn (Book $b) => TitleMatcher::isMatch($query, $b->title . ' ' . $b->author))
            ->sortByDesc(fn (Book $b) => TitleMatcher::score($query, $b->title . ' ' . $b->author))
            ->take(BooksService::MAX_RESULTS)
            ->map(fn (Book $b) => $b->toLookupResult())
            ->values()
            ->all();
    }

    /** @return array{0: list<array>, 1: list<array>} [matches, others] */
    private function split(string $query, ?string $isbn, array $results): array
    {
        if ($isbn) {
            // An ISBN search only counts when the result really has that ISBN
            // (Google sometimes returns a different book for "isbn:...").
            $wanted = self::isbn13($isbn);
            [$same, $different] = collect($results)->partition(
                fn ($book) => $book['isbn'] && self::isbn13(BooksService::normaliseIsbn($book['isbn']) ?? '') === $wanted
            );

            return [$same->values()->all(), $different->values()->all()];
        }

        $matches = [];
        $others = [];
        foreach ($results as $book) {
            $text = $book['title'] . ' ' . ($book['author'] ?? '');
            if (TitleMatcher::isMatch($query, $text)) {
                $matches[] = $book + ['score' => TitleMatcher::score($query, $text)];
            } else {
                $others[] = $book;
            }
        }
        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [array_map(fn ($m) => array_diff_key($m, ['score' => 1]), $matches), $others];
    }

    /** ISBN-10 -> ISBN-13 (ISBN-13 unchanged), so both forms of the same book compare equal. */
    public static function isbn13(string $isbn): string
    {
        if (strlen($isbn) !== 10) {
            return $isbn;
        }

        $core = '978' . substr($isbn, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $core[$i] * ($i % 2 ? 3 : 1);
        }

        return $core . ((10 - $sum % 10) % 10);
    }

    /**
     * Part 5 cover order for real matches: Google Books thumbnail -> Open Library cover by ISBN
     * (?default=false, 404 = none) -> [seller's photo is added by the UI] -> placeholder.
     */
    private function withCovers(array $matches): array
    {
        $needCover = array_filter(array_map(
            fn ($m) => $m['cover_url'] === null || $m['cover_source'] !== 'google_books' ? $m['isbn'] : null,
            $matches
        ));
        $olCovers = $needCover ? $this->books->openLibraryCovers(array_values($needCover)) : [];

        return array_map(function (array $m) use ($olCovers) {
            if ($m['cover_source'] !== 'google_books' && $m['isbn'] && ! empty($olCovers[$m['isbn']])) {
                $m['cover_url'] = $olCovers[$m['isbn']];
                $m['cover_source'] = 'open_library';
            }
            $m['cover_path'] = $m['cover_url'];
            $m['cover_url'] = Covers::url($m['cover_url']);

            return $m;
        }, $matches);
    }
}
