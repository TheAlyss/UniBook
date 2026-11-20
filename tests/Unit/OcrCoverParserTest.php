<?php

namespace Tests\Unit;

use App\Services\OcrService;
use PHPUnit\Framework\TestCase;

/** Part 4: guessing title, authors and edition from raw OCR text of a cover. */
class OcrCoverParserTest extends TestCase
{
    /** Typical OCR text of a UiTM Mandarin module cover (Chinese lines, noisy author line). */
    private const MANDARIN_COVER = "BAHASA MANDARIN\nUNTUK PENGURUSAN\n管理华语\nLevel 1\nLim Swee Hong +2 | Tan Ai Ling* | (T4 Ong Bee Lian\n华语 第一册\nUiTM Press";

    public function test_mandarin_module_cover(): void
    {
        $this->assertSame([
            'title' => 'Bahasa Mandarin untuk Pengurusan',
            'author' => 'Lim Swee Hong, Tan Ai Ling, Ong Bee Lian',
            'edition' => 'Level 1',
        ], OcrService::parseCover(self::MANDARIN_COVER));
    }

    public function test_title_stops_at_the_first_non_uppercase_line(): void
    {
        $parsed = OcrService::parseCover("PRINCIPLES OF\nACCOUNTING\nA practical guide\nFINAL WORDS");

        $this->assertSame('Principles of Accounting', $parsed['title']);
    }

    public function test_uppercase_author_names_are_title_cased(): void
    {
        $parsed = OcrService::parseCover("MANAGEMENT\nAHMAD BIN ALI | SITI AMINAH *");

        $this->assertSame('Ahmad Bin Ali, Siti Aminah', $parsed['author']);
    }

    public function test_edition_formats(): void
    {
        $this->assertSame('2nd Edition', OcrService::parseCover("ECONOMICS\n2nd edition")['edition']);
        $this->assertSame('Third Edition', OcrService::parseCover("ECONOMICS\nTHIRD EDITION")['edition']);
        $this->assertSame('Edisi Kedua', OcrService::parseCover("EKONOMI\nEdisi kedua")['edition']);
        $this->assertNull(OcrService::parseCover("ECONOMICS")['edition']);
    }

    public function test_chinese_only_text_gives_nothing(): void
    {
        $this->assertSame(['title' => null, 'author' => null, 'edition' => null], OcrService::parseCover("管理华语\n第一册"));
    }

    public function test_chinese_lines_are_not_used_in_title_search(): void
    {
        $this->assertSame('BAHASA MANDARIN UNTUK PENGURUSAN', OcrService::toTitleQuery(self::MANDARIN_COVER, 2));
    }
}
