<?php

namespace Tests\Unit\Validators\Rules;

use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonNameRuleTest extends TestCase
{
    public static function validNames(): array
    {
        return [
            ['José María'],
            ['Nguyễn Văn Anh'],
            ["O'Brien"],
            ['Anne-Marie'],
            ['Łukasz Müller'],
            ['王小明'],
            ['山田 太郎'],
            ['김민준'],
            ['Александр'],
            ['Δημήτρης'],
            ['محمد عبد الله'],
            ['דוד'],
            ['अर्जुन'],
            ['ஸ்ரீநிவாசன்'],
            ['สมชาย'],
            ['M.Abdul Rozak'],
            ['J. R. R.'],
            ['Henry 8th'],
            ['ʻIolani'],
            ['Mary/John Smith'],
            ['Acme | Box Office'],
            ['Sam [guest]'],
        ];
    }

    public static function invalidNames(): array
    {
        return [
            ['Your crypto purchase USD 364 success via PayPal. INV ID UTRX4 If unauthorized call +1(805)891-5965 xvc'],
            ['Call 1 805 891 5965'],
            ['Call ٨٠٥٨٩١٥٩٦٥'],
            ['visit evil.com'],
            ['https://x.y'],
            ['www.scam'],
            ['<img src=x>'],
            ['me@example'],
            ['free gift t.ly/abc'],
        ];
    }

    #[DataProvider('validNames')]
    public function test_accepts_real_names(string $name): void
    {
        $this->assertFalse($this->validate($name)->fails());
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_names_carrying_urls_phone_numbers_or_markup(string $name): void
    {
        $this->assertTrue($this->validate($name)->fails());
    }

    public function test_rejects_names_longer_than_the_maximum(): void
    {
        $this->assertTrue($this->validate(str_repeat('a', PersonNameRule::MAX_LENGTH + 1))->fails());
    }

    public function test_an_unchanged_stored_name_is_accepted_even_if_it_breaks_the_rules(): void
    {
        $legacyName = 'Guest 12345 '.str_repeat('a', PersonNameRule::MAX_LENGTH);

        $unchanged = Validator::make(['name' => $legacyName], ['name' => PersonNameRule::rules(currentValue: $legacyName)]);
        $changed = Validator::make(['name' => $legacyName.'b'], ['name' => PersonNameRule::rules(currentValue: $legacyName)]);

        $this->assertFalse($unchanged->fails());
        $this->assertTrue($changed->fails());
    }

    public function test_optional_rules_allow_null(): void
    {
        $validator = Validator::make(['name' => null], ['name' => PersonNameRule::rules(required: false)]);

        $this->assertFalse($validator->fails());
    }

    private function validate(string $name): \Illuminate\Validation\Validator
    {
        return Validator::make(['name' => $name], ['name' => PersonNameRule::rules()]);
    }
}
