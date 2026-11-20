<?php

namespace App\Support;

/**
 * Part 3: is a search result really the book the seller is looking for?
 *
 * Compares the words of the search text with the words of the result's title (+ subtitle and
 * authors). A result only counts as a match when enough words overlap:
 *   - 1 to 3 search words -> every word must appear
 *   - 4+ search words     -> at least 60% of the words must appear (OCR text may contain extra words)
 * Words match exactly or by prefix (min. 4 letters), e.g. "network" ~ "networking".
 *
 * Example: "Bahasa Mandarin Pengurusan" vs "Bahasa Mandarin 2" -> 2 of 3 words -> not a match.
 */
class TitleMatcher
{
    /** Very common words that say nothing about which book it is (English + Malay). */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'by', 'at', 'from', 'as', 'is',
        'dan', 'di', 'ke', 'dari', 'untuk', 'yang', 'dengan', 'pada', 'oleh', 'atau',
        'edition', 'edisi', 'ed', 'vol', 'volume', 'book', 'buku',
    ];

    /** @return list<string> lowercase significant words */
    public static function words(string $text): array
    {
        $text = mb_strtolower($text);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $parts,
            // Small numbers ("2", "4") are noise, but longer ones are real title words ("1984").
            fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, self::STOP_WORDS, true) && (! ctype_digit($w) || mb_strlen($w) >= 3)
        )));
    }

    /** How many of the search words appear in the candidate text. */
    public static function overlap(string $query, string $candidate): int
    {
        $candidateWords = self::words($candidate);
        $count = 0;

        foreach (self::words($query) as $word) {
            foreach ($candidateWords as $other) {
                if ($word === $other || self::samePrefix($word, $other)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    public static function isMatch(string $query, string $candidate): bool
    {
        $total = count(self::words($query));
        if ($total === 0) {
            return false;
        }

        $hits = self::overlap($query, $candidate);

        return $total <= 3 ? $hits === $total : $hits >= (int) ceil($total * 0.6);
    }

    /** 0..1, used to sort matches (best first). */
    public static function score(string $query, string $candidate): float
    {
        $total = count(self::words($query));

        return $total === 0 ? 0.0 : self::overlap($query, $candidate) / $total;
    }

    private static function samePrefix(string $a, string $b): bool
    {
        $short = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $long = $short === $a ? $b : $a;

        return mb_strlen($short) >= 4 && str_starts_with($long, $short);
    }
}
