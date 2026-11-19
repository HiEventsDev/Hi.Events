<?php

namespace Tests\Unit\Enterprise\Crm\Sms;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\SmsCountry;
use Tests\TestCase;

class SmsCountryTest extends TestCase
{
    public function test_texts_only_go_out_during_the_day_in_every_timezone_of_the_country(): void
    {
        $this->assertTrue(SmsCountry::IE->allowsSendingAt(Carbon::parse('2026-11-17 12:00:00', 'UTC')));
        $this->assertFalse(SmsCountry::IE->allowsSendingAt(Carbon::parse('2026-11-17 21:00:00', 'UTC')));
        $this->assertFalse(SmsCountry::US->allowsSendingAt(Carbon::parse('2026-11-17 15:00:00', 'UTC')));
        $this->assertTrue(SmsCountry::US->allowsSendingAt(Carbon::parse('2026-11-17 20:00:00', 'UTC')));
    }

    public function test_the_next_sending_time_is_the_start_of_the_next_window(): void
    {
        $this->assertTrue(Carbon::parse('2026-11-17 09:00:00', 'UTC')->equalTo(SmsCountry::GB->nextSendingTime(Carbon::parse('2026-11-17 02:10:00', 'UTC'))));
    }

    public function test_only_us_numbers_opt_out_by_replying(): void
    {
        $this->assertTrue(SmsCountry::US->optsOutByReply());
        $this->assertFalse(SmsCountry::AU->optsOutByReply());
    }

    public function test_unknown_countries_are_never_sendable(): void
    {
        $this->assertSame(['IE'], SmsCountry::sendableNow(['IE', 'CA'], Carbon::parse('2026-11-17 12:00:00', 'UTC')));
    }
}
