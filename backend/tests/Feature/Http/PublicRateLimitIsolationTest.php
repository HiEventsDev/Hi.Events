<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicRateLimitIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
        config([
            'app.api_rate_limit_per_minute' => 180,
            'app.public_promo_code_rate_limit_per_minute' => 10,
            'app.public_order_rate_limit_per_minute' => 60,
        ]);
    }

    public function test_browsing_an_event_does_not_use_up_the_promo_code_allowance(): void
    {
        for ($attempt = 0; $attempt < 14; $attempt++) {
            $this->getJson('/public/events/1/occurrences');
        }

        $this->assertNotSame(429, $this->getJson('/public/events/1/promo-codes/NOPE')->status());
        $this->assertNotSame(429, $this->getJson('/public/events/1/waitlist')->status());
    }

    public function test_the_seat_map_loads_on_every_page_view_like_the_event_itself(): void
    {
        $statuses = [];

        for ($attempt = 0; $attempt < 70; $attempt++) {
            $statuses[] = $this->getJson('/public/events/1/seat-map')->status();
        }

        $this->assertNotContains(429, $statuses);
    }

    public function test_polling_seat_availability_does_not_use_up_the_shared_allowance(): void
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $this->getJson('/public/events/1/occurrences/1/seat-availability');
        }

        $this->assertNotSame(429, $this->getJson('/public/events/1/occurrences')->status());
    }

    public function test_the_promo_code_route_still_throttles_its_own_traffic(): void
    {
        $statuses = [];

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $statuses[] = $this->getJson('/public/events/1/promo-codes/NOPE')->status();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_the_promo_code_allowance_follows_its_configured_limit(): void
    {
        config(['app.public_promo_code_rate_limit_per_minute' => 3]);
        $statuses = [];

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $statuses[] = $this->getJson('/public/events/1/promo-codes/NOPE')->status();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 3));
        $this->assertSame(429, $statuses[3]);
    }

    public function test_creating_orders_is_throttled_per_minute_without_touching_other_routes(): void
    {
        config(['app.public_order_rate_limit_per_minute' => 5]);
        $statuses = [];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $statuses[] = $this->postJson('/public/events/1/order')->status();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 5));
        $this->assertSame(429, $statuses[5]);
        $this->assertNotSame(429, $this->getJson('/public/events/1/promo-codes/NOPE')->status());
    }
}
