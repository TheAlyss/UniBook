<?php

namespace App\Services;

use App\Exceptions\OcrFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only class in UniBook that talks to OCR.space.
 *
 * extractText()  : image -> raw text (or throws OcrFailedException)
 * findIsbn()     : raw text -> ISBN printed near the barcode, if any
 * toTitleQuery() : raw text -> cleaned search query for BooksService
 */
class OcrService
{
    private const URL = 'https://api.ocr.space/parse/image';

    /** OCR.space free plan limit. Images are resized in the browser before upload. */
    public const MAX_BYTES = 1024 * 1024;

    public function extractText(UploadedFile $image): string
    {
        if ($image->getSize() > self::MAX_BYTES) {
            throw new OcrFailedException('The photo is too large to scan. Please use a photo under 1 MB.');
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['apikey' => config('services.ocr_space.key')])
                ->attach('file', $image->get(), 'cover.' . ($image->guessExtension() ?: 'jpg'))
                ->post(self::URL, [
                    'language' => 'eng',
                    'OCREngine' => '2',        // engine 2 handles stylised cover fonts better
                    'scale' => 'true',
                    'detectOrientation' => 'true',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('OCR.space unreachable', ['error' => $e->getMessage()]);
            throw new OcrFailedException('The scanning service is not responding. Please try again or type the details manually.');
        }

        if ($response->status() === 429) {
            Log::warning('OCR.space rate limit reached');
            throw new OcrFailedException('The scanning service is busy right now. Please wait a minute and try again, or type the details manually.');
        }

        if ($response->failed() || $response->json('IsErroredOnProcessing')) {
            Log::warning('OCR.space request failed', [
                'status' => $response->status(),
                'error' => $response->json('ErrorMessage'),
            ]);
            throw new OcrFailedException('The scanning service could not read this photo. Please try again or type the details manually.');
        }

        $text = collect($response->json('ParsedResults', []))->pluck('ParsedText')->implode("\n");

        return self::stripServiceNotes($text);
    }

    /** The shared demo key sometimes prepends "[Note: ... demo key ...]" to the text. */
    public static function stripServiceNotes(string $text): string
    {
        return trim(preg_replace('/^\s*\[Note:[^\]]*\]\s*/i', '', $text));
    }

    /** Finds an ISBN-13 or ISBN-10 printed in the text (e.g. "ISBN 978-1-4493-5806-8"). */
    public static function findIsbn(string $text): ?string
    {
        // Common OCR confusions, fixed only when the letter sits between digits/separators
        // (so "58O6" -> "5806", but words like "Call" are left alone).
        $text = preg_replace_callback(
            '/(?<=[\d\s-])[OoIl](?=[\d\s-])/',
            fn ($m) => in_array($m[0], ['O', 'o'], true) ? '0' : '1',
            $text
        );

        // Lookarounds stop us from taking 10 digits out of the middle of a longer number.
        if (preg_match_all('/(?<!\d)(?:97[89][\s-]?)?(?:\d[\s-]?){9}[\dX](?![\dX])/i', $text, $matches)) {
            foreach ($matches[0] as $candidate) {
                $isbn = BooksService::normaliseIsbn($candidate);
                if ($isbn !== null && self::isValidIsbn($isbn)) {
                    return $isbn;
                }
            }
        }

        return null;
    }

    /**
     * Turns raw cover text into a short search query: keep the first few lines
     * that contain real words, drop noise such as "edition", URLs and prices.
     */
    public static function toTitleQuery(string $text, int $maxLines = 3, int $maxLength = 80): string
    {
        $noise = '/\b(edition|edisi|ed\.|isbn|www\.\S+|https?:\S+|rm\s?\d+\S*|\d+(st|nd|rd|th))\b/i';

        $lines = collect(preg_split('/\R/u', $text))
            ->reject(fn ($line) => preg_match('/\p{Han}/u', $line)) // Chinese lines are not searched
            ->map(fn ($line) => preg_replace($noise, ' ', $line))
            ->map(fn ($line) => preg_replace('/[^\pL\pN&:\'\- ]/u', ' ', $line))
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line)))
            ->filter(fn ($line) => preg_match_all('/\pL/u', $line) >= 3)
            ->take($maxLines);

        return mb_substr($lines->implode(' '), 0, $maxLength);
    }

    /**
     * Search queries to try in order, from most to least specific
     * (author names or subtitles on the cover can stop a search from matching).
     *
     * @return array<int, string>
     */
    public static function candidateQueries(string $text): array
    {
        return collect([3, 2, 1])
            ->map(fn ($lines) => self::toTitleQuery($text, $lines))
            ->filter(fn ($query) => mb_strlen($query) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Part 4: best guess of title, authors and edition from raw cover text, used to auto-fill the
     * form when no book matches. The seller must still check every field.
     *
     *  - Title:   the consecutive UPPERCASE Latin lines at the top, joined, in Title Case
     *  - Authors: lines with names separated by "|", noise such as "+2", "*", "(T4" removed
     *  - Edition: a line mentioning Level / Edition / Edisi
     *  - Chinese (Han) lines are ignored
     *
     * @return array{title: ?string, author: ?string, edition: ?string}
     */
    public static function parseCover(string $text): array
    {
        $lines = collect(preg_split('/\R/u', self::stripServiceNotes($text)))
            ->map(fn ($l) => trim(preg_replace('/\s+/u', ' ', $l)))
            ->filter(fn ($l) => $l !== '' && ! preg_match('/\p{Han}/u', $l))
            ->values();

        $edition = null;
        $authors = [];
        $titleLines = [];
        $titleDone = false;

        foreach ($lines as $line) {
            $isEdition = preg_match('/\b(level|edition|edisi)\b/iu', $line) === 1;
            $isAuthors = str_contains($line, '|');

            if ($isEdition && $edition === null) {
                $edition = self::editionFrom($line);
            }
            if ($isAuthors) {
                $authors = array_merge($authors, self::namesFrom($line));
            }

            if ($titleDone) {
                continue;
            }
            if (! $isEdition && ! $isAuthors && self::isUppercaseLatin($line)) {
                $titleLines[] = $line;
            } elseif ($titleLines !== []) {
                $titleDone = true; // the run of uppercase lines has ended
            }
        }

        $title = $titleLines === [] ? null : self::titleCase(implode(' ', $titleLines));

        return [
            'title' => $title,
            'author' => $authors === [] ? null : implode(', ', array_unique($authors)),
            'edition' => $edition,
        ];
    }

    /** At least 3 Latin letters, no lowercase letters, not an ISBN line. */
    private static function isUppercaseLatin(string $line): bool
    {
        return preg_match_all('/\p{Latin}/u', $line) >= 3
            && preg_match('/\p{Ll}/u', $line) === 0
            && ! preg_match('/\bISBN\b/u', $line);
    }

    /** "Lim Swee Hong +2 | Tan Ai Ling* | (T4 Ong Bee Lian" -> three clean names. */
    private static function namesFrom(string $line): array
    {
        return collect(explode('|', $line))
            ->map(function ($name) {
                $name = preg_replace('/\+\s*\d+/u', ' ', $name);          // "+2"
                $name = preg_replace('/\(\s*[\p{L}]?\d*\s*\)?/u', ' ', $name); // "(T4", "(2)"
                $name = preg_replace('/[^\p{L}\s\'\.-]/u', ' ', $name);   // "*", digits, symbols
                $name = trim(preg_replace('/\s+/u', ' ', $name), " .-'");

                return preg_match('/\p{Ll}/u', $name) ? $name : self::titleCase($name, false);
            })
            ->filter(fn ($name) => preg_match_all('/\p{L}/u', $name) >= 2)
            ->values()
            ->all();
    }

    private static function editionFrom(string $line): string
    {
        $patterns = [
            '/\blevel\s*([0-9]+|[IVX]+)\b/iu' => fn ($m) => 'Level ' . strtoupper($m[1]),
            '/\b([0-9]+(?:st|nd|rd|th)?|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|revised|new)\s+edition\b/iu'
                // "2nd" stays lowercase (title case would give "2Nd"); words become "Third".
                => fn ($m) => (ctype_digit($m[1][0]) ? strtolower($m[1]) : self::titleCase($m[1], false)) . ' Edition',
            '/\bedition\s*([0-9]+)/iu' => fn ($m) => 'Edition ' . $m[1],
            '/\bedisi\s+([\p{L}0-9]+)/iu' => fn ($m) => 'Edisi ' . self::titleCase($m[1], false),
        ];

        foreach ($patterns as $pattern => $format) {
            if (preg_match($pattern, $line, $m)) {
                return mb_substr($format($m), 0, 50);
            }
        }

        return mb_substr(self::titleCase($line, false), 0, 50);
    }

    /** "BAHASA MANDARIN UNTUK PENGURUSAN" -> "Bahasa Mandarin untuk Pengurusan" */
    private static function titleCase(string $text, bool $smallWords = true): string
    {
        $small = ['a', 'an', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'dan', 'di', 'ke', 'untuk', 'dalam', 'dengan'];
        $words = explode(' ', mb_convert_case(mb_strtolower($text), MB_CASE_TITLE));

        foreach ($words as $i => $word) {
            if ($smallWords && $i > 0 && in_array(mb_strtolower($word), $small, true)) {
                $words[$i] = mb_strtolower($word);
            }
        }

        return implode(' ', $words);
    }

    /** Checksum validation, so random numbers on a cover are not treated as an ISBN. */
    public static function isValidIsbn(string $isbn): bool
    {
        if (strlen($isbn) === 13) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) $isbn[$i] * ($i % 2 ? 3 : 1);
            }
            return (10 - $sum % 10) % 10 === (int) $isbn[12];
        }

        if (strlen($isbn) === 10) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $digit = ($i === 9 && strtoupper($isbn[9]) === 'X') ? 10 : (int) $isbn[$i];
                $sum += $digit * (10 - $i);
            }
            return $sum % 11 === 0;
        }

        return false;
    }
}
