<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiMatchService;
use App\Services\BooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The AI (OpenRouter) has one job: label the photo text as title / authors / edition / publisher,
 * copying only words that are on the photo. It does not rank results or write book details.
 * All APIs are faked.
 */
class AiMatchTest extends TestCase
{
    use RefreshDatabase;

    private const OCR = 'api.ocr.space/*';
    private const GOOGLE = 'www.googleapis.com/*';
    private const OPEN_LIBRARY = 'openlibrary.org/*';
    private const AI = 'openrouter.ai/*';

    private const COVER = "ISLAMIC\nFINANCIAL\nSYSTEM\nPrinciples & Operations\nSecond Edition\nISRA";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('covers');
        Storage::fake('scans');
        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.models' => ['model-a:free', 'model-b:free'],
        ]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
    }

    private static function chat(array|string $content): array
    {
        return ['choices' => [['message' => ['content' => is_string($content) ? $content : json_encode($content)]]]];
    }

    private static function aiRequests(): int
    {
        return count(Http::recorded(fn (Request $r) => str_contains($r->url(), 'openrouter.ai')));
    }

    private function fakeApis(mixed $ai): void
    {
        Http::fake([
            self::OCR => Http::response(['ParsedResults' => [['ParsedText' => self::COVER]], 'IsErroredOnProcessing' => false]),
            self::GOOGLE => Http::response(['items' => [
                ['id' => 'kassim01', 'volumeInfo' => ['title' => 'Islamic Financial System', 'subtitle' => 'Theory and Practice', 'authors' => ['Salina H. Kassim'], 'publishedDate' => '2020']],
                ['id' => 'isra0001', 'volumeInfo' => ['title' => 'Islamic Financial System', 'subtitle' => 'Principles & Operations', 'authors' => ['ISRA'], 'publisher' => 'ISRA', 'publishedDate' => '2016']],
            ]]),
            self::OPEN_LIBRARY => Http::response('', 404),
            self::AI => $ai,
        ]);
    }

    public function test_ai_labels_the_photo_text_using_only_words_on_the_photo(): void
    {
        // "Guide" and the made-up publisher "Pearson" are NOT on the photo, so they must be removed.
        $this->fakeApis(Http::response(self::chat([
            'title' => 'Islamic Financial System: Principles & Operations Guide',
            'authors' => 'ISRA',
            'edition' => 'Second Edition',
            'publisher' => 'Pearson',
        ])));

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('autofill_source', 'ai')
            ->assertJsonPath('ai_model', 'model-a:free')
            ->assertJsonPath('ai_note', null)
            ->assertJsonPath('autofill.title', 'Islamic Financial System: Principles & Operations')
            ->assertJsonPath('autofill.author', 'ISRA')
            ->assertJsonPath('autofill.edition', 'Second Edition')
            ->assertJsonPath('autofill.publisher', null)
            // The AI title + author is the search query; results are NOT ranked by AI.
            ->assertJsonPath('query', 'Islamic Financial System: Principles & Operations ISRA')
            ->assertJsonMissingPath('results.0.confidence');

        $this->assertSame(1, self::aiRequests()); // one AI call per scan: labelling only
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'openrouter.ai')
            && str_contains($r['messages'][0]['content'], 'Label which words are the title')
            && $r['response_format'] === ['type' => 'json_object']);
    }

    public function test_ai_title_alone_is_searched_when_title_plus_authors_finds_nothing(): void
    {
        // Local modules: the authors on the cover often differ from the online record.
        Http::fake([
            self::OCR => Http::response(['ParsedResults' => [['ParsedText' => "ASAS\nKEUSAHAWANAN\nModul Pembelajaran\nNor Aziah | Faridah Musa"]], 'IsErroredOnProcessing' => false]),
            self::AI => Http::response(self::chat(['title' => 'Asas Keusahawanan: Modul Pembelajaran', 'authors' => 'Nor Aziah, Faridah Musa'])),
            self::GOOGLE => fn (Request $r) => $r['q'] === 'Asas Keusahawanan: Modul Pembelajaran'
                ? Http::response(['items' => [['volumeInfo' => ['title' => 'Asas Keusahawanan', 'subtitle' => 'Modul Pembelajaran', 'authors' => ['Someone Else']]]]])
                : Http::response(['totalItems' => 0]),
            self::OPEN_LIBRARY => Http::response(['docs' => []]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('query', 'Asas Keusahawanan: Modul Pembelajaran')
            ->assertJsonPath('results.0.title', 'Asas Keusahawanan: Modul Pembelajaran');
    }

    public function test_the_same_photo_text_is_labelled_once_then_served_from_the_cache(): void
    {
        $this->fakeApis(Http::response(self::chat(['title' => 'Islamic Financial System', 'authors' => 'ISRA'])));
        $user = User::factory()->create();

        foreach ([1, 2] as $_) {
            $this->actingAs($user)->postJson('/listings/scan', ['image' => $this->photo()])
                ->assertOk()
                ->assertJsonPath('autofill_source', 'ai')
                ->assertJsonPath('autofill.author', 'ISRA');
        }

        $this->assertSame(1, self::aiRequests());
    }

    public function test_next_approved_model_is_tried_when_the_first_fails(): void
    {
        Http::fake([
            self::AI => fn (Request $r) => $r['model'] === 'model-a:free'
                ? Http::response(['error' => 'busy'], 503)
                : Http::response(self::chat("```json\n{\"title\":\"ISLAMIC FINANCIAL SYSTEM\",\"authors\":\"ISRA\"}\n```")),
        ]);

        $ai = app(AiMatchService::class);
        $labels = $ai->readCover(self::COVER);

        $this->assertSame('ISLAMIC FINANCIAL SYSTEM', $labels['title']);
        $this->assertSame('model-b:free', $ai->lastModel());
    }

    public function test_daily_free_limit_pauses_the_ai_and_falls_back_to_text_rules(): void
    {
        $this->fakeApis(Http::response(['error' => [
            'message' => 'Rate limit exceeded: free-models-per-day.',
            'code' => 429,
            'metadata' => ['headers' => ['X-RateLimit-Reset' => (string) ((time() + 7200) * 1000)], 'limit_source' => 'openrouter_free_tier_daily'],
        ]], 429));
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('autofill_source', 'rules')        // parseCover rules instead
            ->assertJsonPath('autofill.title', 'Islamic Financial System')
            ->assertJsonPath('autofill.edition', 'Second Edition')
            ->assertJsonPath('query', 'ISLAMIC FINANCIAL SYSTEM');
        $note = $this->postJson('/listings/scan', ['image' => $this->photo()])->json('ai_note');

        $this->assertStringStartsWith('AI is resting: the free daily limit is used up (back at ', $note);
        // Only ONE AI request in total: model-b was not tried, and the second scan did not call the AI.
        $this->assertSame(1, self::aiRequests());
        $this->assertNotNull(app(AiMatchService::class)->pausedUntil());
    }

    public function test_ai_error_falls_back_to_text_rules_with_a_note(): void
    {
        $this->fakeApis(Http::response('Service unavailable', 503));

        $this->actingAs(User::factory()->create())
            ->postJson('/listings/scan', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('autofill_source', 'rules')
            ->assertJsonPath('ai_model', null)
            ->assertJsonPath('ai_note', 'AI is not available right now. The details below were read by text rules, so please check them.')
            ->assertJsonCount(2, 'results'); // the normal relevance filter still finds the books
    }

    public function test_invalid_ai_json_is_treated_as_a_failure(): void
    {
        Http::fake([self::AI => Http::response(self::chat('Sure! The title is Islamic Financial System.'))]);

        $ai = app(AiMatchService::class);
        $this->assertNull($ai->readCover(self::COVER));
        $this->assertSame('failed', $ai->status());
    }

    public function test_no_key_means_no_ai_call(): void
    {
        config(['services.openrouter.key' => null]);
        Http::fake();

        $this->assertNull(app(AiMatchService::class)->readCover(self::COVER));
        Http::assertNothingSent();
    }

    public function test_picked_book_details_come_from_google_volume_and_open_library_edition(): void
    {
        Http::fake([
            'www.googleapis.com/books/v1/volumes/isra0001*' => Http::response(['volumeInfo' => ['title' => 'Islamic Financial System', 'publisher' => 'International Shari\'ah Research Academy', 'publishedDate' => '2016-01-01']]),
            'openlibrary.org/isbn/9789670960068.json' => Http::response(['edition_name' => '2nd ed.', 'publishers' => ['Other']]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/listings/book-details?google_id=isra0001&isbn=9789670960068')
            ->assertOk()
            ->assertExactJson(['author' => null, 'edition' => '2nd ed.', 'publisher' => 'International Shari\'ah Research Academy', 'published_year' => '2016']);
    }

    public function test_edition_is_taken_from_the_title_when_no_api_has_one(): void
    {
        $this->assertSame('13th Edition', BooksService::editionFromTitle('Database Systems (13th Edition)'));
        $this->assertSame('Second Edition', BooksService::editionFromTitle('Islamic Financial System, second edition'));
        $this->assertNull(BooksService::editionFromTitle('Atomic Habits'));
    }
}
