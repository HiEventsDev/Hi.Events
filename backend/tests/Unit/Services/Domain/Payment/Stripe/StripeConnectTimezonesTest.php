<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\Services\Domain\Payment\Stripe\StripeConnectTimezones;
use Tests\TestCase;

class StripeConnectTimezonesTest extends TestCase
{
    public function test_lists_timezones_in_countries_stripe_connect_does_not_support(): void
    {
        $unsupported = (new StripeConnectTimezones)->unsupported();

        $this->assertContains('Asia/Kolkata', $unsupported);
        $this->assertContains('Asia/Jakarta', $unsupported);
        $this->assertContains('Africa/Lagos', $unsupported);
        $this->assertContains('America/Sao_Paulo', $unsupported);
    }

    public function test_keeps_timezones_in_supported_countries_and_ones_without_a_country(): void
    {
        $unsupported = (new StripeConnectTimezones)->unsupported();

        $this->assertNotContains('America/New_York', $unsupported);
        $this->assertNotContains('Australia/Sydney', $unsupported);
        $this->assertNotContains('Europe/Dublin', $unsupported);
        $this->assertNotContains('America/Mexico_City', $unsupported);
        $this->assertNotContains('America/Puerto_Rico', $unsupported);
        $this->assertNotContains('UTC', $unsupported);
    }
}
