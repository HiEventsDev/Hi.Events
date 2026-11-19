<?php

namespace Tests\Unit\Validators\Rules;

use HiEvents\DomainObjects\Enums\PhoneCollection;
use HiEvents\Validators\Rules\MobilePhoneNumberRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MobilePhoneNumberRuleTest extends TestCase
{
    public function test_required_collection_needs_a_valid_mobile_number(): void
    {
        $rules = ['phone' => MobilePhoneNumberRule::rulesFor(PhoneCollection::REQUIRED)];

        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make(['phone' => '+44 20 7946 0958'], $rules)->fails());
        $this->assertFalse(Validator::make(['phone' => '+44 7911 123456'], $rules)->fails());
    }

    public function test_optional_collection_allows_no_number_but_not_a_bad_one(): void
    {
        $rules = ['phone' => MobilePhoneNumberRule::rulesFor(PhoneCollection::OPTIONAL)];

        $this->assertFalse(Validator::make([], $rules)->fails());
        $this->assertFalse(Validator::make(['phone' => null], $rules)->fails());
        $this->assertTrue(Validator::make(['phone' => '12345'], $rules)->fails());
    }

    public function test_numbers_are_not_checked_when_the_event_does_not_collect_them(): void
    {
        $this->assertSame([], MobilePhoneNumberRule::rulesFor(PhoneCollection::OFF));
    }
}
