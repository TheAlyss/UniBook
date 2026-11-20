<?php

namespace Tests\Feature;

use App\Exceptions\OcrFailedException;
use App\Models\User;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integration tests: OcrService and POST /listings/scan against faked OCR.space,
 * Google Books and Open Library responses.
 */
class OcrScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('covers'); // never write test photos into public/uploads
        Storage::fake('scans');
    }

    private const OCR = 'api.ocr.space/*';
    private const GOOGLE = 'www.googleapis.com/*';
    private const OPEN_LIBRARY = 'openlibrary.org/*';

    /** A real 1x1 PNG, so Laravel's "image" validation passes without GD. */
    private function photo(string $name = 'cover.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
    }

    private function ocrResponse(string $text): array
    {
        return ['ParsedResults' => [['ParsedText' => $text]], 'IsErroredOnProcessing' => false];
    }

    // ---- OcrService ----

    public function test_ocr_service_returns_parsed_text_and_sends_api_key(): void
    {
        config(['services.ocr_space.key' => 'test-key']);
        Http::fake([self::OCR => Http::response($this->ocrResponse("RESTful Web APIs\n"))]);

        $this->assertSame('RESTful Web APIs', app(OcrService::class)->extractText($this->photo()));
        Http::assertSent(fn (Request $r) => $r->hasHeader('apikey', 'test-key') && $r->isMultipart());
    }

    public function test_ocr_service_throws_when_ocr_space_reports_an_error(): void
    {
        Http::fake([self::OCR => Http::response(['IsErroredOnProcessing' => true, 'ErrorMessage' => ['Bad image']])]);

        $this->expectException(OcrFailedException::class);
        app(OcrService::class)->extractText($this->photo());
    }

    public function test_ocr_service_throws_on_timeout(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(OcrFailedException::class);
        app(OcrService::class)->extractText($this->photo());
    }

    public function test_ocr_service_rejects_files_over_1mb_without_calling_the_api(): void
    {
        Http::fake();

        try {
            app(OcrService::class)->extractText(UploadedFile::fake()->create('big.jpg', 1500, 'image/jpeg'));
            $this->fail('Expected OcrFailedException');
        } catch (OcrFailedException $e) {
            $this->assertStringContainsString('under 1 MB', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    // ---- POST /listings/scan ----

    public function test_guests_cannot_scan(): void
    {
        $this->postJson('/listings/scan', ['image' => $this->photo()])->assertUnauthorized();
    }

    public function test_scan_with_printed_isbn_searches_books_by_isbn(): void
    {
        Http::fake([
            self::OCR => Http::response($this->ocrResponse("RESTful Web APIs\nISBN 978-1-4493-5806-8")),
            self::GOOGLE => Http::response(['items' => [['volumeInfo' => [
                'title' => 'RESTful Web APIs',
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9781449358068']],
                'imageLinks' => ['thumbnail' => 'https://books.google.com/cover.jpg'],
            ]]]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('isbn', '9781449358068')
            ->assertJsonPath('results.0.title', 'RESTful Web APIs')
            ->assertJsonPath('message', 'ISBN 9781449358068 found. 1 match found. Choose the correct book, or "None of these".');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'googleapis.com') && $r['q'] === 'isbn:9781449358068');
    }

    public function test_scan_without_isbn_searches_by_cleaned_title(): void
    {
        Http::fake([
            self::OCR => Http::response($this->ocrResponse("Computer Networking\n8th Edition\nKurose")),
            self::GOOGLE => Http::response(['items' => [['volumeInfo' => [
                'title' => 'Computer Networking',
                'authors' => ['James Kurose'],
                'imageLinks' => ['thumbnail' => 'https://books.google.com/cover.jpg'],
            ]]]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('isbn', null)
            ->assertJsonPath('query', 'Computer Networking Kurose');
    }

    public function test_scan_retries_with_a_shorter_query_when_the_full_cover_text_finds_nothing(): void
    {
        Http::fake([
            self::OCR => Http::response($this->ocrResponse("Computer Networking\nA Top-Down Approach\nJames Kurose")),
            self::GOOGLE => Http::response(['totalItems' => 0]),
            self::OPEN_LIBRARY => function (Request $r) {
                return $r['q'] === 'Computer Networking A Top-Down Approach'
                    ? Http::response(['docs' => [['title' => 'Computer Networking: A Top-Down Approach']]])
                    : Http::response(['docs' => []]);
            },
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('query', 'Computer Networking A Top-Down Approach')
            ->assertJsonPath('results.0.title', 'Computer Networking: A Top-Down Approach');
    }

    public function test_scan_returns_404_with_ocr_text_when_no_book_matches(): void
    {
        Http::fake([
            self::OCR => Http::response($this->ocrResponse('Modul Pengaturcaraan Web')),
            self::GOOGLE => Http::response(['totalItems' => 0]),
            self::OPEN_LIBRARY => Http::response(['docs' => []]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertNotFound()
            ->assertJsonPath('ocr_text', 'Modul Pengaturcaraan Web')
            ->assertJsonPath('message', 'We read the text but could not find a matching book. Please fill in the details manually.');
    }

    public function test_scan_returns_422_when_no_text_is_read(): void
    {
        Http::fake([self::OCR => Http::response($this->ocrResponse(''))]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertUnprocessable()
            ->assertJsonPath('results', []);
    }

    public function test_scan_returns_503_when_ocr_service_fails(): void
    {
        Http::fake([self::OCR => Http::response('Server error', 500)]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The scanning service could not read this photo. Please try again or type the details manually.');
    }

    public function test_scan_explains_when_ocr_rate_limit_is_reached(): void
    {
        Http::fake([self::OCR => Http::response('Too many requests', 429)]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The scanning service is busy right now. Please wait a minute and try again, or type the details manually.');
    }

    public function test_scan_rejects_non_image_files(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'The file must be a photo (JPG, PNG or WEBP).']);

        Http::assertNothingSent();
    }

    // ---- Part 4 / Part 5: auto-fill and the seller's photo ----

    public function test_no_match_returns_autofill_from_cover_text_and_saves_photo(): void
    {
        Http::fake([
            self::OCR => Http::response($this->ocrResponse("BAHASA MANDARIN\nUNTUK PENGURUSAN\n管理华语\nLevel 1\nLim Swee Hong +2 | Tan Ai Ling*")),
            self::GOOGLE => Http::response(['items' => [['volumeInfo' => ['title' => 'Principles of Management']]]]),
            self::OPEN_LIBRARY => Http::response(['docs' => []]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertNotFound()
            ->assertJsonPath('results', [])
            ->assertJsonPath('other_results.0.title', 'Principles of Management')
            ->assertJsonPath('autofill.title', 'Bahasa Mandarin untuk Pengurusan')
            ->assertJsonPath('autofill.author', 'Lim Swee Hong, Tan Ai Ling')
            ->assertJsonPath('autofill.edition', 'Level 1')
            ->assertJsonPath('photo.cover_source', 'seller_photo')
            ->assertJsonPath('message', 'No matching book found. Check what we read from your photo below, then fill the form with it.');

        $path = $response->json('photo.path');
        // Kept temporarily only: nothing is written to public/uploads until the listing is posted.
        $this->assertMatchesRegularExpression('#^scan/[a-f0-9-]+\.png$#', $path);
        Storage::disk('scans')->assertExists(basename($path));
        $this->assertSame([], Storage::disk('covers')->files());
    }

    public function test_shorter_query_results_must_still_match_the_full_cover_title(): void
    {
        Http::fake([
            self::OCR => Http::response($this->ocrResponse("BAHASA MANDARIN\nUNTUK PENGURUSAN\nLevel 1")),
            // Only the general query "BAHASA MANDARIN" returns something, and it is a different book.
            self::GOOGLE => function (Request $r) {
                return $r['q'] === 'BAHASA MANDARIN'
                    ? Http::response(['items' => [['volumeInfo' => ['title' => 'Bahasa Mandarin 2']]]])
                    : Http::response(['totalItems' => 0]);
            },
            self::OPEN_LIBRARY => Http::response(['docs' => []]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertNotFound()
            ->assertJsonPath('results', [])
            ->assertJsonPath('other_results.0.title', 'Bahasa Mandarin 2')
            ->assertJsonPath('autofill.title', 'Bahasa Mandarin untuk Pengurusan');
    }

    public function test_photo_is_kept_even_when_ocr_fails(): void
    {
        Http::fake([self::OCR => Http::response('busy', 429)]);

        $path = $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertStatus(503)
            ->json('photo.path');

        Storage::disk('scans')->assertExists(basename($path));
    }

    public function test_listing_can_use_the_seller_photo_as_cover_and_delete_removes_it(): void
    {
        Http::fake([self::OCR => Http::response($this->ocrResponse(''))]);
        $user = User::factory()->create();

        $scan = $this->actingAs($user)->postJson('/listings/scan', ['image' => $this->photo()])->json('photo.path');
        $this->get(route('listings.scan-photo', basename($scan)))->assertOk(); // preview while it is temporary

        $this->post(route('listings.store'), [
            'title' => 'Bahasa Mandarin untuk Pengurusan', 'author' => 'Lim Swee Hong', 'category' => 'Textbook',
            'price' => 20, 'condition' => 'Good', 'cover_url' => $scan, 'cover_source' => 'seller_photo', 'book_source' => 'manual',
        ])->assertSessionHasNoErrors();

        // Posting moved the photo from the temporary folder to public/uploads/covers.
        $path = 'uploads/covers/' . basename($scan);
        Storage::disk('scans')->assertMissing(basename($scan));
        Storage::disk('covers')->assertExists(basename($scan));

        $listing = $user->listings()->firstOrFail();
        $this->assertSame($path, $listing->cover_url);
        $this->assertSame('seller_photo', $listing->cover_source);
        $this->assertStringEndsWith('/' . $path, $listing->coverImageUrl());

        // The catalogue keeps the same file, so deleting the listing must NOT delete it.
        $this->delete(route('listings.destroy', $listing));
        Storage::disk('covers')->assertExists(basename($path));

        // Once nothing uses it any more, it is removed.
        \App\Models\Book::query()->update(['cover_url' => null]);
        $other = $this->makeListingWithCover($user, $path);
        $other->delete();
        Storage::disk('covers')->assertMissing(basename($path));
    }

    public function test_reset_deletes_the_sellers_own_temporary_scan_only(): void
    {
        Http::fake([self::OCR => Http::response($this->ocrResponse(''))]);
        $seller = User::factory()->create();

        $name = basename($this->actingAs($seller)->postJson('/listings/scan', ['image' => $this->photo()])->json('photo.path'));
        Storage::disk('scans')->assertExists($name);

        // Another user cannot delete it (it is not in their session).
        $this->flushSession();
        $this->actingAs(User::factory()->create())
            ->deleteJson(route('listings.scan-photo.discard', $name))
            ->assertNotFound();
        Storage::disk('scans')->assertExists($name);
    }

    public function test_reset_button_discards_the_scan(): void
    {
        Http::fake([self::OCR => Http::response($this->ocrResponse(''))]);
        $seller = User::factory()->create();

        $name = basename($this->actingAs($seller)->postJson('/listings/scan', ['image' => $this->photo()])->json('photo.path'));

        $this->deleteJson(route('listings.scan-photo.discard', $name))->assertNoContent();
        Storage::disk('scans')->assertMissing($name);
        $this->assertSame([], Storage::disk('covers')->files()); // nothing public was ever written
    }

    public function test_prune_deletes_old_unused_scans_and_photos_but_keeps_the_rest(): void
    {
        $old = time() - 25 * 3600;
        foreach (['old-scan.jpg', 'new-scan.jpg'] as $f) {
            Storage::disk('scans')->put($f, 'x');
        }
        foreach (['old-unused.jpg', 'old-used.jpg', 'new-unused.jpg'] as $f) {
            Storage::disk('covers')->put($f, 'x');
        }
        foreach ([['scans', 'old-scan.jpg'], ['covers', 'old-unused.jpg'], ['covers', 'old-used.jpg']] as [$disk, $f]) {
            touch(Storage::disk($disk)->path($f), $old);
        }
        $this->makeListingWithCover(User::factory()->create(), 'uploads/covers/old-used.jpg');

        $this->artisan('covers:prune')
            ->expectsOutput('Deleted 1 unused scan(s) and 1 unused cover photo(s).')
            ->assertSuccessful();

        Storage::disk('scans')->assertMissing('old-scan.jpg');
        Storage::disk('scans')->assertExists('new-scan.jpg');       // seller may still post it
        Storage::disk('covers')->assertMissing('old-unused.jpg');
        Storage::disk('covers')->assertExists('old-used.jpg');      // a listing uses it
        Storage::disk('covers')->assertExists('new-unused.jpg');    // too new to remove
    }

    public function test_listing_can_be_posted_with_the_open_library_cover_chosen_by_the_seller(): void
    {
        // "Wrong cover?": the seller replaced the Google Books picture with the Open Library one.
        $user = User::factory()->create();
        $url = 'https://covers.openlibrary.org/b/isbn/9780132350884-M.jpg?default=false';

        $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'Clean Code', 'category' => 'Textbook', 'price' => 30, 'condition' => 'Good',
            'isbn' => '9780132350884', 'cover_url' => $url, 'cover_source' => 'open_library', 'book_source' => 'google_books',
        ])->assertSessionHasNoErrors();

        $listing = $user->listings()->firstOrFail();
        $this->assertSame($url, $listing->cover_url);
        $this->assertSame('open_library', $listing->cover_source);
    }

    public function test_fake_cover_paths_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('listings.store'), [
            'title' => 'X', 'author' => 'Y', 'category' => 'Others', 'price' => 5, 'condition' => 'Good',
            'cover_url' => 'uploads/covers/../../.env',
        ])->assertSessionHasErrors(['cover_url' => 'The cover must be an https image link or a photo uploaded to UniBook.']);
    }

    private function makeListingWithCover(User $user, string $path): \App\Models\Listing
    {
        return $user->listings()->create([
            'title' => 'Temp', 'author' => 'A', 'category' => 'Others', 'price' => 5, 'condition' => 'Good',
            'cover_url' => $path, 'cover_source' => 'seller_photo',
        ]);
    }
}
