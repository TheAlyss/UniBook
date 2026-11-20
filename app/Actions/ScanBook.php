<?php

namespace App\Actions;

use App\Exceptions\OcrFailedException;
use App\Services\AiMatchService;
use App\Services\BookLookupService;
use App\Services\BooksService;
use App\Services\OcrService;
use App\Support\Covers;
use App\Support\TitleMatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Photo of a book -> matching books for the seller to choose from.
 * Shared by the website (POST /listings/scan) and the API (POST /api/listings/scan).
 *
 *   1. Keep the seller's photo TEMPORARILY (storage/app/private/scans). It becomes public only when
 *      the listing is posted (Covers::promote), and unused scans are deleted after 24 hours.
 *   2. OCR.space reads the text.
 *   3. A printed ISBN (checksum verified) is searched exactly, no AI needed.
 *   4. Otherwise the AI labels the OCR text (title, authors, edition, publisher), copying only words
 *      on the photo. Without AI (off, daily limit, error) the OcrService::parseCover() rules are used.
 *   5. Search query: AI title + authors, then the first 3/2/1 lines of the cover. Our catalogue +
 *      Google Books (up to 10), or Open Library, give the candidates; the relevance filter
 *      (TitleMatcher) decides which are real matches. Results are NOT ranked by AI.
 *   6. `autofill` (the labels) is always returned. The seller clicks "Fill the form from my photo" /
 *      "None of these" to use it; nothing is filled by itself.
 */
class ScanBook
{
    public function __construct(
        private OcrService $ocr,
        private BookLookupService $lookup,
        private AiMatchService $ai,
    ) {
    }

    /** @return array{status:int, body:array} */
    public function handle(UploadedFile $image): array
    {
        // OCR + one AI call (each model tried in turn) + book search can take a while.
        @set_time_limit(180);

        // Temporary only (not public). It is moved to public/uploads/covers when the listing is posted.
        Covers::pruneTempQuietly();
        $photoPath = Covers::storeTempPhoto($image);
        $photo = ['path' => $photoPath, 'url' => Covers::url($photoPath), 'cover_source' => 'seller_photo'];

        try {
            $text = $this->ocr->extractText($image);
        } catch (OcrFailedException $e) {
            return ['status' => 503, 'body' => ['message' => $e->getMessage(), 'results' => [], 'other_results' => [], 'photo' => $photo]];
        }

        if ($text === '') {
            return ['status' => 422, 'body' => [
                'message' => 'No text could be read from this photo. Try a clearer, well-lit photo, or type the details manually.',
                'ocr_text' => '',
                'results' => [],
                'other_results' => [],
                'photo' => $photo,
            ]];
        }

        $isbn = OcrService::findIsbn($text);
        $guess = OcrService::parseCover($text) + ['publisher' => null];

        // A printed ISBN is searched first. Otherwise (or if the ISBN finds nothing) the cover text,
        // labelled by the AI (only words on the photo), or by the parseCover rules without AI.
        $labels = null;
        $found = $isbn ? $this->byIsbn($isbn) : null;
        if ($found === null || $found['results'] === []) {
            $labels = $this->ai->readCover($text);
            $found = $this->byCoverText($text, $labels['title'] ?? $guess['title'], AiMatchService::queryFrom($labels), $labels['title'] ?? null);
        }

        $fields = $labels ?? $guess;
        $autofill = array_filter($fields) ? $fields + ['isbn' => $isbn] : null;

        $count = count($found['results']);
        $message = match (true) {
            $count > 0 => ($found['isbn'] ? "ISBN {$found['isbn']} found. " : '') . $count . ' ' . Str::plural('match', $count) . ' found. Choose the correct book, or "None of these".',
            $autofill !== null => 'No matching book found. Check what we read from your photo below, then fill the form with it.',
            default => 'We read the text but could not find a matching book. Please fill in the details manually.',
        };

        return ['status' => $count > 0 ? 200 : 404, 'body' => [
            'message' => $message,
            'ocr_text' => $text,
            'isbn' => $isbn,
            'query' => $found['query'],
            'source' => $found['source'],
            'results' => $found['results'],
            'other_results' => array_slice($found['others'], 0, BooksService::SCAN_RESULTS),
            'autofill' => $autofill,
            'autofill_source' => $autofill ? ($labels ? 'ai' : 'rules') : null,
            'ai_model' => $labels ? $this->ai->lastModel() : null,
            // Why the photo text was read by rules instead of AI (null when the AI was used or not needed).
            'ai_note' => $labels || $found['isbn'] ? null : $this->aiNote(),
            'photo' => $photo,
        ]];
    }

    /** A printed ISBN is exact, so the normal lookup is enough. */
    private function byIsbn(string $isbn): array
    {
        $found = $this->lookup->lookup($isbn);

        return ['query' => $isbn, 'isbn' => $isbn, 'source' => $found['source'], 'results' => $found['matches'], 'others' => $found['others']];
    }

    /** Why the photo text was not labelled by AI, in words for the seller. */
    private function aiNote(): string
    {
        return match ($this->ai->status()) {
            'daily_limit' => 'AI is resting: the free daily limit is used up'
                . (($until = $this->ai->pausedUntil()) ? ' (back at ' . $until->setTimezone('Asia/Kuala_Lumpur')->format('g:i A') . ')' : '')
                . '. The details below were read by text rules, so please check them.',
            'off' => 'AI is switched off. The details below were read by text rules, so please check them.',
            default => 'AI is not available right now. The details below were read by text rules, so please check them.',
        };
    }

    private function byCoverText(string $text, ?string $titleGuess, ?string $aiQuery, ?string $aiTitle = null): array
    {
        // Search queries, most specific first: AI title + authors, then the AI title alone (the authors
        // printed on local modules often differ from the online record), then the first 3/2/1 cover lines.
        $queries = array_values(array_unique(array_filter(array_merge([$aiQuery, $aiTitle], OcrService::candidateQueries($text)))));
        $others = [];

        foreach ($queries as $query) {
            $candidates = $this->lookup->candidates($query);
            if ($candidates['books'] === []) {
                continue;
            }

            // The relevance filter decides the real matches. Results found with a shorter query
            // must still match the full title read from the cover.
            [$matches, $unrelated] = $this->lookup->relevance($query, $candidates['books']);
            if ($titleGuess !== null) {
                [$keep, $drop] = collect($matches)->partition(
                    fn ($b) => TitleMatcher::isMatch($titleGuess, $b['title'] . ' ' . ($b['author'] ?? ''))
                );
                $matches = $keep->values()->all();
                $unrelated = array_merge($drop->values()->all(), $unrelated);
            }

            $others = array_merge($others, $unrelated);
            if ($matches !== []) {
                return ['query' => $query, 'isbn' => null, 'source' => $candidates['source'], 'results' => $matches, 'others' => self::unique($others, $matches)];
            }
        }

        return ['query' => $queries[0] ?? '', 'isbn' => null, 'source' => null, 'results' => [], 'others' => self::unique($others, [])];
    }

    /** Other results without duplicates or books already shown as matches. */
    private static function unique(array $others, array $matches): array
    {
        $seen = [];
        foreach ($matches as $m) {
            $seen[mb_strtolower($m['title'] . '|' . ($m['isbn'] ?? ''))] = true;
        }

        return array_values(array_filter($others, function ($b) use (&$seen) {
            $key = mb_strtolower($b['title'] . '|' . ($b['isbn'] ?? ''));
            if (isset($seen[$key])) {
                return false;
            }

            return $seen[$key] = true;
        }));
    }

    public static function rules(): array
    {
        return ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']];
    }

    public static function messages(): array
    {
        return [
            'image.required' => 'Please choose a photo of the book.',
            'image.image' => 'The file must be a photo (JPG, PNG or WEBP).',
            'image.mimes' => 'The file must be a photo (JPG, PNG or WEBP).',
            'image.max' => 'The photo may not be larger than 5 MB.',
        ];
    }
}
