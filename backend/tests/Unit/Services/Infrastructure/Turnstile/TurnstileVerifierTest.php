<?php

namespace Tests\Unit\Services\Infrastructure\Turnstile;

use HiEvents\Services\Infrastructure\Turnstile\TurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Log\NullLogger;
use Tests\TestCase;

class TurnstileVerifierTest extends TestCase
{
    public function test_passes_when_turnstile_is_not_configured(): void
    {
        config(['services.turnstile.secret_key' => null]);
        Http::fake();

        $this->assertTrue($this->verifier()->verify(null, '1.2.3.4'));
        Http::assertNothingSent();
    }

    public function test_rejects_a_missing_token_when_configured(): void
    {
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake();

        $this->assertFalse($this->verifier()->verify(null, '1.2.3.4'));
        Http::assertNothingSent();
    }

    public function test_accepts_a_token_cloudflare_confirms(): void
    {
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->assertTrue($this->verifier()->verify('token', '1.2.3.4'));
        Http::assertSent(fn (Request $request) => $request['secret'] === 'secret'
            && $request['response'] === 'token'
            && $request['remoteip'] === '1.2.3.4');
    }

    public function test_rejects_a_token_cloudflare_refuses(): void
    {
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->assertFalse($this->verifier()->verify('token', '1.2.3.4'));
    }

    public function test_lets_signups_through_when_cloudflare_cannot_be_reached(): void
    {
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertTrue($this->verifier()->verify('token', '1.2.3.4'));
    }

    private function verifier(): TurnstileVerifier
    {
        return new TurnstileVerifier(app(Factory::class), app('config'), new NullLogger);
    }
}
