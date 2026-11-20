<?php

namespace Database\Seeders;

use App\Services\BookLookupService;
use App\Support\TitleMatcher;
use Illuminate\Database\Seeder;

/**
 * Part 6: fills the `books` catalogue with well-known, real books across all categories.
 *
 * The details are NOT typed in here. Each book goes through the normal book lookup
 * (books table -> Google Books -> Open Library), exactly like a seller's search, and only
 * a real match is saved. Books that are not found are reported and skipped.
 *
 * Run: php artisan db:seed --class=BookCatalogSeeder   (needs internet)
 */
class BookCatalogSeeder extends Seeder
{
    /** [search text (ISBN or title + author), expected title (for the report), category] */
    public const BOOKS = [
        ['9780062315007', 'The Alchemist (Paulo Coelho)', 'Novel'],
        ['9780061120084', 'To Kill a Mockingbird (Harper Lee)', 'Novel'],
        ['9780451524935', '1984 / Nineteen Eighty-Four (George Orwell)', 'Novel'],
        ['Interlok Abdullah Hussain', 'Interlok (Abdullah Hussain)', 'Novel'],
        ['9780132350884', 'Clean Code (Robert C. Martin)', 'Textbook'],
        ['9780262033848', 'Introduction to Algorithms, Third Edition (Cormen et al.)', 'Textbook'],
        ['9780205309023', 'The Elements of Style (Strunk & White)', 'Reference'],
        ["Oxford Advanced Learner's Dictionary", "Oxford Advanced Learner's Dictionary", 'Reference'],
        ['Riyad as-Salihin Nawawi', 'Riyad as-Salihin (Imam an-Nawawi)', 'Religious'],
        ['9780735211292', 'Atomic Habits (James Clear)', 'Others'],
    ];

    private const SOURCE_NAMES = [
        'catalog' => 'UniBook catalogue (already saved)',
        'google_books' => 'Google Books',
        'open_library' => 'Open Library',
    ];

    public function run(BookLookupService $lookup): void
    {
        $rows = [];

        foreach (self::BOOKS as [$query, $expected, $category]) {
            $found = $lookup->lookup($query);
            $match = $found['matches'][0] ?? null;

            if ($match === null) {
                $rows[] = [$category, $expected, $query, 'NOT FOUND', '-', '-'];
                continue;
            }

            // Safety check for test data: the main title found (before ":") must really be the book
            // we meant, not e.g. a study ABOUT that book. Wrong matches are reported, not saved.
            $mainTitle = trim(explode(':', $match['title'])[0]);
            if (! TitleMatcher::isMatch($mainTitle, $expected)) {
                $rows[] = [$category, $expected, $query, 'WRONG MATCH (' . (self::SOURCE_NAMES[$found['source']] ?? $found['source']) . '), not saved', mb_strimwidth($match['title'], 0, 45, '...'), '-'];
                continue;
            }

            $book = $lookup->remember($match, $match['source_key'], $match['book_id'] ?? null);

            $rows[] = [
                $category,
                $expected,
                $query,
                self::SOURCE_NAMES[$found['source']] ?? $found['source'],
                mb_strimwidth($book->title, 0, 45, '...'),
                $book->cover_source ?? 'placeholder',
            ];
        }

        $this->command?->table(['Category', 'Book', 'Searched with', 'Found by', 'Saved title', 'Cover'], $rows);
    }
}
