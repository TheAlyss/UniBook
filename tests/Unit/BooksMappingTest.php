<?php

namespace Tests\Unit;

use App\Services\BooksService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests: transforming external JSON into the UniBook book schema.
 */
class BooksMappingTest extends TestCase
{
    public function test_google_volume_is_mapped_to_unibook_schema(): void
    {
        $book = BooksService::mapGoogleVolume([
            'volumeInfo' => [
                'title' => 'RESTful Web APIs',
                'subtitle' => 'Services for a Changing World',
                'authors' => ['Leonard Richardson', 'Mike Amundsen'],
                'publisher' => "O'Reilly Media",
                'publishedDate' => '2013-09-12',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '1449358063'],
                    ['type' => 'ISBN_13', 'identifier' => '9781449358068'],
                ],
                'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=abc&zoom=1&edge=curl'],
            ],
        ]);

        $this->assertSame([
            'title' => 'RESTful Web APIs: Services for a Changing World',
            'author' => 'Leonard Richardson, Mike Amundsen',
            'edition' => null,
            'publisher' => "O'Reilly Media",
            'published_year' => '2013',
            'isbn' => '9781449358068',
            'cover_url' => 'https://books.google.com/books/content?id=abc&zoom=1',
            'cover_source' => 'google_books',
            'source' => 'Google Books',
            'source_key' => 'google_books',
            'google_id' => null,
        ], $book);
    }

    public function test_google_volume_with_missing_fields_does_not_break(): void
    {
        $book = BooksService::mapGoogleVolume(['volumeInfo' => ['title' => 'Untitled Notes']]);

        $this->assertSame('Untitled Notes', $book['title']);
        $this->assertNull($book['author']);
        $this->assertNull($book['isbn']);
        $this->assertNull($book['cover_url']);
    }

    public function test_open_library_doc_is_mapped_and_prefers_the_searched_isbn(): void
    {
        $book = BooksService::mapOpenLibraryDoc([
            'title' => 'RESTful Web APIs',
            'author_name' => ['Leonard Richardson', 'Michael Amundsen', 'Sam Ruby', 'Extra Person'],
            'publisher' => ["O'Reilly Media"],
            'first_publish_year' => 2013,
            'isbn' => ['9781306811590', '9781449358068'],
            'cover_i' => 8514661,
        ], '9781449358068');

        $this->assertSame('Leonard Richardson, Michael Amundsen, Sam Ruby', $book['author']);
        $this->assertSame('9781449358068', $book['isbn']);
        $this->assertSame('2013', $book['published_year']);
        $this->assertSame('https://covers.openlibrary.org/b/id/8514661-M.jpg', $book['cover_url']);
        $this->assertSame('Open Library', $book['source']);
    }

    public function test_isbn_detection(): void
    {
        $this->assertSame('9781449358068', BooksService::normaliseIsbn('978-1-4493-5806-8'));
        $this->assertSame('144935806X', BooksService::normaliseIsbn('144935806x'));
        $this->assertNull(BooksService::normaliseIsbn('RESTful Web APIs'));
        $this->assertNull(BooksService::normaliseIsbn('12345'));
    }
}
