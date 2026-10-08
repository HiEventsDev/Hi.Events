<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientIpAndRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.trusted_proxies' => '*',
            'app.ssr_shared_secret' => null,
        ]);

        Route::get('/_test/client-ip', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'ssr_key_forwarded' => $request->headers->has('X-Hi-Ssr-Key'),
        ]);
    }

    public function test_any_forwarded_for_is_trusted_by_default_so_existing_installs_behind_a_proxy_keep_working(): void
    {
        $this->assertSame('203.0.113.9', $this->clientIp(['X-Forwarded-For' => '203.0.113.9'], '10.0.0.5'));
    }

    public function test_configured_proxies_stop_clients_spoofing_their_ip(): void
    {
        config(['app.trusted_proxies' => '10.0.0.0/8']);

        $this->assertSame('198.51.100.7', $this->clientIp(['X-Forwarded-For' => '6.6.6.6'], '198.51.100.7'));
        $this->assertSame('203.0.113.9', $this->clientIp(['X-Forwarded-For' => '6.6.6.6, 203.0.113.9'], '10.0.0.5'));
    }

    public function test_the_cloudflare_keyword_trusts_cloudflare_edges_but_not_what_the_client_sent(): void
    {
        config(['app.trusted_proxies' => '127.0.0.1,cloudflare']);

        $this->assertSame(
            '203.0.113.9',
            $this->clientIp(['X-Forwarded-For' => '6.6.6.6, 203.0.113.9, 173.245.48.5'], '127.0.0.1'),
        );
        $this->assertSame(
            '203.0.113.9',
            $this->clientIp(['X-Forwarded-For' => '6.6.6.6, 203.0.113.9, 2606:4700::1'], '127.0.0.1'),
        );
    }

    public function test_by_default_the_ssr_server_passes_on_the_visitor_ip_without_any_setup(): void
    {
        $this->assertSame('203.0.113.9', $this->clientIp(['X-Forwarded-For' => '203.0.113.9'], '127.0.0.1'));
    }

    public function test_with_locked_down_proxies_the_shared_secret_vouches_for_the_visitor_ip_the_ssr_server_sent(): void
    {
        config(['app.ssr_shared_secret' => 'ssr-secret', 'app.trusted_proxies' => '127.0.0.1,cloudflare']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/_test/client-ip', [
                'X-Forwarded-For' => '203.0.113.9, 198.51.100.20, 173.245.48.5',
                'X-Forwarded-Proto' => 'https',
                'X-Hi-Ssr-Key' => 'ssr-secret',
            ])
            ->assertJsonPath('ip', '203.0.113.9')
            ->assertJsonPath('secure', true)
            ->assertJsonPath('ssr_key_forwarded', false);
    }

    public function test_without_the_right_secret_locked_down_proxies_ignore_what_the_client_claims(): void
    {
        config(['app.ssr_shared_secret' => 'ssr-secret', 'app.trusted_proxies' => '127.0.0.1,cloudflare']);
        $headers = ['X-Forwarded-For' => '203.0.113.9, 198.51.100.20, 173.245.48.5'];

        $this->assertSame('198.51.100.20', $this->clientIp([...$headers, 'X-Hi-Ssr-Key' => 'wrong'], '127.0.0.1'));

        config(['app.ssr_shared_secret' => null]);

        $this->assertSame('198.51.100.20', $this->clientIp([...$headers, 'X-Hi-Ssr-Key' => ''], '127.0.0.1'));
    }

    public function test_an_invalid_visitor_ip_is_ignored_even_with_the_secret(): void
    {
        config(['app.ssr_shared_secret' => 'ssr-secret', 'app.trusted_proxies' => '127.0.0.1']);

        $this->assertSame('127.0.0.1', $this->clientIp([
            'X-Forwarded-For' => 'not-an-ip',
            'X-Hi-Ssr-Key' => 'ssr-secret',
        ], '127.0.0.1'));
    }

    public function test_an_array_email_is_rejected_by_validation_not_by_the_rate_limiter(): void
    {
        $this->postJson('/auth/login', ['email' => ['a@example.com'], 'password' => 'x'])->assertStatus(422);
        $this->postJson('/auth/forgot-password', ['email' => ['a@example.com']])->assertStatus(422);
    }

    public function test_page_views_rendered_for_different_visitors_do_not_share_a_rate_limit(): void
    {
        config(['app.api_rate_limit_per_minute' => 2]);

        foreach (['203.0.113.1', '203.0.113.2', '203.0.113.3'] as $visitorIp) {
            for ($view = 0; $view < 2; $view++) {
                $status = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                    ->getJson('/public/events/1/occurrences', ['X-Forwarded-For' => $visitorIp])
                    ->status();

                $this->assertNotSame(429, $status);
            }
        }
    }

    public function test_stripe_webhooks_are_never_throttled(): void
    {
        config(['app.api_rate_limit_per_minute' => 1]);
        $statuses = [];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $statuses[] = $this->postJson('/public/webhooks/stripe', [], ['Stripe-Signature' => 'invalid'])->status();
        }

        $this->assertNotContains(429, $statuses);
        $this->assertNotSame(429, $this->getJson('/public/events/1/occurrences')->status());
        $this->assertSame(429, $this->getJson('/public/events/1/occurrences')->status());
    }

    public function test_login_attempts_are_limited_per_email_whatever_ip_they_claim(): void
    {
        $statuses = [];

        for ($attempt = 0; $attempt < 11; $attempt++) {
            $statuses[] = $this->postJson('/auth/login', ['email' => 'Victim@Example.com', 'password' => 'wrong'], [
                'X-Forwarded-For' => '198.51.100.'.$attempt,
            ])->status();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 10));
        $this->assertSame(429, $statuses[10]);
        $this->assertNotSame(429, $this->postJson('/auth/login', ['email' => 'someone-else@example.com', 'password' => 'wrong'])->status());
    }

    public function test_password_reset_emails_are_limited_per_address(): void
    {
        $statuses = [];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $statuses[] = $this->postJson('/auth/forgot-password', ['email' => 'victim@example.com'], [
                'X-Forwarded-For' => '198.51.100.'.$attempt,
            ])->status();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 5));
        $this->assertSame(429, $statuses[5]);
    }

    public function test_each_door_device_has_its_own_allowance_on_a_shared_venue_ip(): void
    {
        config(['app.api_rate_limit_per_minute' => 2]);
        $sessions = app(BoxOfficeSessionService::class);
        $tokens = [];

        foreach ([1, 2] as $device) {
            $tokens[] = $sessions->create(
                boxOffice: (new BoxOfficeDomainObject)->setId(990000 + $device)->setEventId(1)->setPinHash('hash'),
                operatorName: 'Door '.$device,
                eventOccurrenceId: null,
                stripeTerminalReaderId: null,
            )->token;
        }

        foreach ($tokens as $token) {
            for ($request = 0; $request < 2; $request++) {
                $this->assertNotSame(429, $this->getJson('/public/events/1/occurrences', ['X-Box-Office-Session' => $token])->status());
            }
        }

        $this->assertSame(429, $this->getJson('/public/events/1/occurrences', ['X-Box-Office-Session' => $tokens[0]])->status());
    }

    public function test_door_devices_run_by_one_signed_in_organizer_each_have_their_own_allowance(): void
    {
        config(['app.api_rate_limit_per_minute' => 2]);
        $this->actingAs((new User)->forceFill(['id' => 424242]), 'api');
        $sessions = app(BoxOfficeSessionService::class);
        $tokens = [];

        foreach ([1, 2] as $device) {
            $tokens[] = $sessions->create(
                boxOffice: (new BoxOfficeDomainObject)->setId(980000 + $device)->setEventId(1)->setPinHash('hash'),
                operatorName: 'Organizer',
                eventOccurrenceId: null,
                stripeTerminalReaderId: null,
                authenticatedUserId: 424242,
                authenticatedAccountId: 1,
            )->token;
        }

        foreach ($tokens as $token) {
            for ($request = 0; $request < 2; $request++) {
                $this->assertNotSame(429, $this->getJson('/public/events/1/occurrences', ['X-Box-Office-Session' => $token])->status());
            }
        }
    }

    public function test_a_made_up_door_session_token_does_not_buy_a_fresh_allowance(): void
    {
        config(['app.api_rate_limit_per_minute' => 2]);

        $this->getJson('/public/events/1/occurrences');
        $this->getJson('/public/events/1/occurrences');

        $this->assertSame(429, $this->getJson('/public/events/1/occurrences', ['X-Box-Office-Session' => 'bos_made_up'])->status());
    }

    private function clientIp(array $headers, string $remoteAddress): string
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->getJson('/_test/client-ip', $headers)
            ->json('ip');
    }
}
