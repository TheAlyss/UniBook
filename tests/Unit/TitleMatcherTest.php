<?php

namespace Tests\Unit;

use App\Support\TitleMatcher;
use PHPUnit\Framework\TestCase;

/** Part 3: only results whose title really overlaps the search text count as matches. */
class TitleMatcherTest extends TestCase
{
    public function test_unrelated_google_results_for_a_local_title_are_not_matches(): void
    {
        $query = 'Bahasa Mandarin Pengurusan';

        foreach ([
            'Pengurusan Kewangan Peribadi',                 // 1 of 3 words
            'Mandarin Chinese for Beginners',               // 1 of 3 words
            'Principles of Management',                     // 0 of 3 words
            'Bahasa Melayu Tingkatan 4',                    // 1 of 3 words
        ] as $title) {
            $this->assertFalse(TitleMatcher::isMatch($query, $title), $title);
        }
    }

    public function test_real_titles_match(): void
    {
        $this->assertTrue(TitleMatcher::isMatch('Bahasa Mandarin Pengurusan', 'Bahasa Mandarin untuk Pengurusan'));
        $this->assertTrue(TitleMatcher::isMatch('Database Systems Coronel', 'Database Systems: Design, Implementation, and Management Carlos Coronel'));
        // Author words in the search text are fine as long as enough title words match.
        $this->assertTrue(TitleMatcher::isMatch('Computer Networking A Top-Down Approach James Kurose', 'Computer Networking: A Top-Down Approach'));
    }

    public function test_prefix_matching_and_stop_words(): void
    {
        $this->assertTrue(TitleMatcher::isMatch('computer network', 'Computer Networking'));
        $this->assertSame(['alchemist'], TitleMatcher::words('The Alchemist'));
        $this->assertTrue(TitleMatcher::isMatch('The Alchemist', 'The Alchemist: 25th Anniversary Edition'));
        $this->assertFalse(TitleMatcher::isMatch('The Alchemist', 'The Hobbit'));
    }

    public function test_three_word_search_needs_every_word(): void
    {
        $this->assertFalse(TitleMatcher::isMatch('Bahasa Mandarin Pengurusan', 'Bahasa Mandarin 2'));
        $this->assertFalse(TitleMatcher::isMatch('Bahasa Mandarin Pengurusan', 'Sikap terhadap penggunaan Bahasa Mandarin'));
    }

    public function test_two_word_search_needs_both_words(): void
    {
        $this->assertFalse(TitleMatcher::isMatch('Calculus Stewart', 'Calculus for Dummies'));
        $this->assertTrue(TitleMatcher::isMatch('Calculus Stewart', 'Calculus: Early Transcendentals James Stewart'));
    }

    public function test_numeric_titles_like_1984_can_be_found(): void
    {
        $this->assertSame(['1984'], TitleMatcher::words('1984'));
        $this->assertTrue(TitleMatcher::isMatch('1984', '1984 George Orwell'));
        $this->assertSame(['tingkatan'], TitleMatcher::words('Tingkatan 4'));
    }

    public function test_empty_or_stop_word_only_query_never_matches(): void
    {
        $this->assertFalse(TitleMatcher::isMatch('', 'Anything'));
        $this->assertFalse(TitleMatcher::isMatch('the and of', 'The Book of And'));
    }
}
