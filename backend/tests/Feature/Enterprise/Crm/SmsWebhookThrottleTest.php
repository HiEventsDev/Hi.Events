<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SmsWebhookThrottleTest extends TestCase
{
    private const FIRST_CONNECTION = 'smsc_aaaaaaaaaaaaaaaaaaaaaaaa';

    private const SECOND_CONNECTION = 'smsc_bbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_each_twilio_connection_has_its_own_webhook_allowance_whatever_address_twilio_calls_from(): void
    {
        $first = $this->limitFor(self::FIRST_CONNECTION, 'status', '54.1.1.1');
        $firstFromAnotherAddress = $this->limitFor(self::FIRST_CONNECTION, 'status', '54.2.2.2');
        $second = $this->limitFor(self::SECOND_CONNECTION, 'status', '54.1.1.1');

        $this->assertSame($first->key, $firstFromAnotherAddress->key);
        $this->assertNotSame($first->key, $second->key);
    }

    public function test_delivery_receipts_can_never_use_up_the_allowance_for_replies(): void
    {
        $receipts = $this->limitFor(self::FIRST_CONNECTION, 'status', '54.1.1.1');
        $replies = $this->limitFor(self::FIRST_CONNECTION, 'inbound', '54.1.1.1');

        $this->assertNotSame($receipts->key, $replies->key);
    }

    public function test_the_allowances_cover_the_fastest_a_campaign_can_be_sent(): void
    {
        $textsPerMinute = CampaignDispatchService::WAVE_SIZE * 60 / CampaignDispatchService::WAVE_DELAY_SECONDS;
        $receipts = $this->limitFor(self::FIRST_CONNECTION, 'status', '54.1.1.1');
        $replies = $this->limitFor(self::FIRST_CONNECTION, 'inbound', '54.1.1.1');

        $this->assertSame(60, $receipts->decaySeconds);
        $this->assertGreaterThanOrEqual(4 * $textsPerMinute, $receipts->maxAttempts);
        $this->assertSame(60, $replies->decaySeconds);
        $this->assertGreaterThanOrEqual($textsPerMinute, $replies->maxAttempts);
    }

    private function limitFor(string $publicId, string $kind, string $ip): Limit
    {
        $request = Request::create("/public/webhooks/sms/twilio/$publicId/$kind", 'POST', server: ['REMOTE_ADDR' => $ip]);
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);

        $middleware = Route::gatherRouteMiddleware($route);
        $this->assertContains(ThrottleRequests::class.':crm-sms-webhook', $middleware);
        $this->assertNotContains(ThrottleRequests::class.':api', $middleware);

        return RateLimiter::limiter('crm-sms-webhook')($request);
    }
}
