<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CheckInRateLimitTest extends TestCase
{
    private const CHECK_IN_LIST = '/public/check-in-lists/cil_missing';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
        config(['app.api_rate_limit_per_minute' => 180]);
    }

    public function test_a_busy_door_behind_one_venue_ip_is_not_held_to_the_shared_api_allowance(): void
    {
        $statuses = [];

        for ($scan = 0; $scan < 50; $scan++) {
            $statuses[] = $this->postJson(self::CHECK_IN_LIST.'/check-ins')->status();
            $statuses[] = $this->getJson(self::CHECK_IN_LIST)->status();
            $statuses[] = $this->getJson(self::CHECK_IN_LIST."/attendees/A-SCAN{$scan}/badge")->status();
            $statuses[] = $this->postJson(self::CHECK_IN_LIST."/attendees/A-SCAN{$scan}/badge/printed")->status();
        }

        $this->assertNotContains(429, $statuses);
    }

    public function test_check_in_traffic_does_not_use_up_the_shared_allowance(): void
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $this->getJson(self::CHECK_IN_LIST.'/stats');
        }

        $this->assertNotSame(429, $this->getJson('/public/events/1')->status());
    }

    public function test_check_in_traffic_still_has_its_own_cap(): void
    {
        for ($attempt = 0; $attempt < 1200; $attempt++) {
            $this->getJson(self::CHECK_IN_LIST.'/stats');
        }

        $this->assertSame(429, $this->getJson(self::CHECK_IN_LIST)->status());
    }
}
