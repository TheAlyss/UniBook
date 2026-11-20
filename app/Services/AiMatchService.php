<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only class in UniBook that talks to OpenRouter (free AI models).
 *
 * The AI has ONE job and never creates book information:
 *   readCover(): label the OCR text of a cover — which words are the title, authors, edition and
 *   publisher. It may only COPY text that is on the photo; any word not in the OCR text is removed.
 *   Title + authors also become the search query (queryFrom()).
 *
 * Search results are NOT ranked by AI; BookLookupService's relevance filter decides the matches.
 *
 * Saving the free daily quota (50 requests/day without credits):
 *   - the same OCR text is labelled once, then served from the cache (1 week);
 *   - when OpenRouter says the daily limit is used up, the AI is paused until the reset time
 *     instead of trying every model (the limit is shared by all models).
 *
 * readCover() returns null when the AI is off, paused or fails; the caller then uses
 * OcrService::parseCover() rules. status() says why, for the message shown to the seller.
 */
class AiMatchService
{
    /** Cache key holding the time (unix seconds) when the daily free limit resets. */
    private const PAUSE_KEY = 'ai:paused-until';

    /** Model that answered the last successful call (shown in the API response for transparency). */
    private ?string $lastModel = null;

    /** Result of the last readCover() call: labelled | cached | daily_limit | failed | off */
    private string $status = 'off';

    public function enabled(): bool
    {
        return filled(config('services.openrouter.key')) && config('services.openrouter.models') !== [];
    }

    public function lastModel(): ?string
    {
        return $this->lastModel;
    }

    public function status(): string
    {
        return $this->status;
    }

    /** When the AI may be used again after the daily free limit was reached, or null if not paused. */
    public function pausedUntil(): ?Carbon
    {
        $until = Cache::get(self::PAUSE_KEY);

        return $until && $until > time() ? Carbon::createFromTimestamp($until) : null;
    }

    /**
     * Labels the OCR text of a cover: which words are the title, authors, edition and publisher.
     *
     * @return array{title: ?string, author: ?string, edition: ?string, publisher: ?string}|null null = no AI labels
     */
    public function readCover(string $ocrText): ?array
    {
        $this->lastModel = null;
        if (! $this->enabled()) {
            $this->status = 'off';

            return null;
        }

        // Same photo text = same labels: don't spend the daily quota again.
        $cacheKey = 'ai:labels:' . md5(mb_strtolower(trim($ocrText)));
        if (is_array($cached = Cache::get($cacheKey))) {
            $this->status = 'cached';
            $this->lastModel = $cached['model'];

            return $cached['labels'];
        }

        if ($this->pausedUntil()) {
            $this->status = 'daily_limit';

            return null;
        }

        $json = $this->ask(
            'You clean OCR text from a photo of a book cover. Label which words are the title, the authors, '
            . 'the edition and the publisher. Return JSON only: '
            . '{"title": "...", "authors": "...", "edition": "...", "publisher": "..."}. '
            . 'Copy words exactly as they appear in the OCR text: you may join broken lines, write titles in Title Case '
            . '(keep acronyms such as ISRA or UiTM in capitals), separate several authors with ", ", and drop noise '
            . '(prices, course codes, slogans, series labels, stray symbols, non-Latin lines). Include the subtitle in the title. '
            . 'Never add, translate or guess words that are not in the text. Use "" when a field is not on the cover.',
            "OCR text:\n" . mb_substr($ocrText, 0, 2000)
        );

        if (! is_array($json)) {
            $this->status = $this->pausedUntil() ? 'daily_limit' : 'failed';

            return null;
        }

        // Only words that are really on the photo are kept.
        $allowed = self::wordSet($ocrText);
        $keep = function ($value, int $max) use ($allowed): ?string {
            $words = collect(preg_split('/\s+/u', is_string($value) ? $value : ''))
                ->filter(fn ($word) => isset($allowed[self::normalise($word)]));
            $text = trim(preg_replace('/\s+/u', ' ', $words->implode(' ')), " ,;|");

            return $text === '' ? null : mb_substr($text, 0, $max);
        };

        $labels = [
            'title' => $keep($json['title'] ?? '', 255),
            'author' => $keep($json['authors'] ?? $json['author'] ?? '', 255),
            'edition' => $keep($json['edition'] ?? '', 50),
            'publisher' => $keep($json['publisher'] ?? '', 255),
        ];

        if (! array_filter($labels)) {
            $this->status = 'failed';

            return null;
        }

        Cache::put($cacheKey, ['labels' => $labels, 'model' => $this->lastModel], now()->addWeek());
        $this->status = 'labelled';

        return $labels;
    }

    /** Search query from the labels: title + authors. */
    public static function queryFrom(?array $labels): ?string
    {
        $query = trim(($labels['title'] ?? '') . ' ' . ($labels['author'] ?? ''));

        return mb_strlen($query) >= 3 ? mb_substr($query, 0, 120) : null;
    }

    /** Sends one chat request, trying each approved model until one returns valid JSON. */
    private function ask(string $system, string $user): ?array
    {
        foreach (config('services.openrouter.models') as $model) {
            try {
                $response = Http::withToken(config('services.openrouter.key'))
                    ->withHeaders(['X-Title' => 'UniBook'])
                    ->timeout(config('services.openrouter.timeout', 20))
                    ->acceptJson()
                    ->post(config('services.openrouter.url'), [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $user],
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('OpenRouter unreachable', ['model' => $model, 'error' => $e->getMessage()]);
                continue;
            }

            if ($this->dailyLimitReached($response)) {
                return null; // shared by all models: trying the next one is pointless
            }

            if ($response->failed()) {
                Log::warning('OpenRouter request failed', ['model' => $model, 'status' => $response->status()]);
                continue;
            }

            $json = self::decode((string) $response->json('choices.0.message.content', ''));
            if ($json !== null) {
                $this->lastModel = $model;

                return $json;
            }
            Log::warning('OpenRouter returned no valid JSON', ['model' => $model]);
        }

        return null;
    }

    /**
     * OpenRouter answers 429 "free-models-per-day" when the account's daily free quota is used up.
     * Then the AI is paused until the reset time it gives (or for an hour if it gives none).
     */
    private function dailyLimitReached(Response $response): bool
    {
        if ($response->status() !== 429) {
            return false;
        }

        $error = (array) $response->json('error', []);
        $isDaily = str_contains((string) ($error['message'] ?? ''), 'per-day')
            || ($error['metadata']['limit_source'] ?? null) === 'openrouter_free_tier_daily';
        if (! $isDaily) {
            return false; // e.g. one busy model: the next model may still work
        }

        $resetMs = (int) ($error['metadata']['headers']['X-RateLimit-Reset'] ?? 0);
        $until = $resetMs > 0 ? intdiv($resetMs, 1000) : time() + 3600;
        Cache::put(self::PAUSE_KEY, $until, Carbon::createFromTimestamp($until));
        Log::warning('OpenRouter daily free limit reached; AI paused', ['until' => date('c', $until)]);

        return true;
    }

    /** Accepts plain JSON or JSON wrapped in ```json fences. */
    public static function decode(string $content): ?array
    {
        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)));
        $json = json_decode($content, true);
        if (! is_array($json) && preg_match('/\{.*\}/s', $content, $m)) {
            $json = json_decode($m[0], true);
        }

        return is_array($json) ? $json : null;
    }

    /** @return array<string, true> */
    private static function wordSet(string $text): array
    {
        $set = [];
        foreach (preg_split('/[^\pL\pN\'&-]+/u', $text) as $word) {
            if (($w = self::normalise($word)) !== '') {
                $set[$w] = true;
            }
        }

        return $set;
    }

    private static function normalise(string $word): string
    {
        return mb_strtolower(trim($word, " \t\n\r\0\x0B.,;:!?()[]\"'"));
    }
}
