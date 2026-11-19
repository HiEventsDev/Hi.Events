<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm\Sms;

use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsLengthCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmsLengthCalculatorTest extends TestCase
{
    #[DataProvider('texts')]
    public function test_a_text_is_measured_the_way_carriers_split_it(string $text, int $characters, int $parts, bool $isUnicode): void
    {
        $length = (new SmsLengthCalculator)->measure($text);

        $this->assertSame([$characters, $parts, $isUnicode], [$length->characters, $length->parts, $length->is_unicode]);
    }

    public static function texts(): array
    {
        return [
            'an empty text' => ['', 0, 1, false],
            'plain text fills one part up to 160' => [str_repeat('a', 160), 160, 1, false],
            'one more character needs a second part' => [str_repeat('a', 161), 161, 2, false],
            'long plain text is split every 153' => [str_repeat('a', 306), 306, 2, false],
            'a character past two full parts needs a third' => [str_repeat('a', 307), 307, 3, false],
            'accented letters in the basic set count once' => ['Café è ù ñ Ü', 12, 1, false],
            'an accented letter outside the basic set makes the text unicode' => ['Côté', 4, 1, true],
            'basic set letters with a line break' => ["Hello\nJosé £5", 13, 1, false],
            'braces, pipes and the euro sign count twice' => ['{}[]|^~\\€', 18, 1, false],
            'an emoji switches to the short alphabet and counts as two' => ['Party 🎉', 8, 1, true],
            'unicode fills one part up to 70' => [str_repeat('я', 70), 70, 1, true],
            'unicode is then split every 67' => [str_repeat('я', 71), 71, 2, true],
            'a curly apostrophe is unicode' => ['Don’t miss it', 13, 1, true],
            'a two-septet character that would straddle a part boundary moves to the next part' => [str_repeat('a', 152).'€'.str_repeat('a', 152), 306, 3, false],
            'an emoji that would straddle a part boundary moves to the next part' => [str_repeat('я', 66).'😀'.str_repeat('я', 66), 134, 3, true],
            'a two-septet character that ends exactly on a part boundary stays in it' => [str_repeat('a', 151).'€'.str_repeat('a', 153), 306, 2, false],
        ];
    }
}
