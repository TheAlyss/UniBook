<?php

namespace Tests\Unit;

use App\Services\OcrService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests: cleaning raw OCR text before it is sent to BooksService.
 */
class OcrTextTest extends TestCase
{
    public function test_finds_isbn_13_with_hyphens(): void
    {
        $this->assertSame('9781449358068', OcrService::findIsbn("RESTful Web APIs\nISBN 978-1-4493-5806-8"));
    }

    public function test_finds_isbn_with_spaces_and_ocr_letter_mistakes(): void
    {
        // OCR misread "0" as "O" and "1" as "l".
        $this->assertSame('9781449358068', OcrService::findIsbn('ISBN-13: 978 l 4493 58O6 8'));
    }

    public function test_finds_isbn_10(): void
    {
        $this->assertSame('1449358063', OcrService::findIsbn('ISBN 1-449-35806-3'));
    }

    public function test_ignores_numbers_with_invalid_checksum(): void
    {
        $this->assertNull(OcrService::findIsbn('Call 9781449358069 for details'));
        $this->assertNull(OcrService::findIsbn('Computer Networking 8th Edition'));
    }

    public function test_title_query_keeps_first_meaningful_lines_and_drops_noise(): void
    {
        $text = "Computer Networking\nA Top-Down Approach\n8th Edition\nJames Kurose\nKeith Ross\nwww.pearson.com";

        $this->assertSame('Computer Networking A Top-Down Approach James Kurose', OcrService::toTitleQuery($text));
    }

    public function test_candidate_queries_go_from_specific_to_short(): void
    {
        $text = "Computer Networking\nA Top-Down Approach\nJames Kurose";

        $this->assertSame([
            'Computer Networking A Top-Down Approach James Kurose',
            'Computer Networking A Top-Down Approach',
            'Computer Networking',
        ], OcrService::candidateQueries($text));

        $this->assertSame(['Database Systems'], OcrService::candidateQueries('Database Systems'));
    }

    public function test_demo_key_note_is_removed_from_ocr_text(): void
    {
        $raw = "[Note: the 'helloworld' demo key is for testing only, not for production. Get your own free OCR API key at https://ocr.space/ocrapi/freekey]\n\nComputer Networking\nKurose";

        $this->assertSame("Computer Networking\nKurose", OcrService::stripServiceNotes($raw));
        $this->assertSame('Computer Networking [2nd]', OcrService::stripServiceNotes('Computer Networking [2nd]'));
    }

    public function test_title_query_drops_lines_without_real_words(): void
    {
        $this->assertSame('Database Systems', OcrService::toTitleQuery("|| ~~ 12\nDatabase Systems\n@ #"));
        $this->assertSame('', OcrService::toTitleQuery("12 34\n--"));
    }
}
