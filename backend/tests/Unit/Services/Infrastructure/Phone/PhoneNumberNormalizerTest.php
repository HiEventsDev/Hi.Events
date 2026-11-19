<?php

namespace Tests\Unit\Services\Infrastructure\Phone;

use HiEvents\Services\Infrastructure\Phone\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    public static function mobileNumbers(): array
    {
        return [
            'US' => ['+1 (415) 555-2671', '+14155552671'],
            'UK' => ['+44 7911 123456', '+447911123456'],
            'Ireland' => ['+353 85 123 4567', '+353851234567'],
            'Australia' => ['+61 412 345 678', '+61412345678'],
        ];
    }

    #[DataProvider('mobileNumbers')]
    public function test_mobile_numbers_are_formatted_as_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PhoneNumberNormalizer)->toMobileE164($input));
    }

    public static function rejectedNumbers(): array
    {
        return [
            'UK landline' => ['+44 20 7946 0958'],
            'no country code' => ['0412 345 678'],
            'too short' => ['+61 412'],
            'not a number' => ['call me'],
            'empty' => [''],
        ];
    }

    #[DataProvider('rejectedNumbers')]
    public function test_numbers_that_cannot_receive_texts_are_rejected(string $input): void
    {
        $this->assertNull((new PhoneNumberNormalizer)->toMobileE164($input));
    }

    public function test_a_local_number_search_matches_the_stored_international_number(): void
    {
        $this->assertSame('412345678', PhoneNumberNormalizer::searchDigits('0412 345 678'));
        $this->assertSame('14155552671', PhoneNumberNormalizer::searchDigits('+1 415-555-2671'));
    }

    public function test_names_emails_and_short_references_are_not_phone_searches(): void
    {
        $this->assertNull(PhoneNumberNormalizer::searchDigits('ray@example.com'));
        $this->assertNull(PhoneNumberNormalizer::searchDigits('Ray Buyer'));
        $this->assertNull(PhoneNumberNormalizer::searchDigits('123'));
    }
}
