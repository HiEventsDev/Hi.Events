<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\LifecycleEmail;

use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailUnsubscribeTokens;
use Illuminate\Config\Repository as ConfigRepository;
use Tests\TestCase;

class LifecycleEmailUnsubscribeTokensTest extends TestCase
{
    private LifecycleEmailUnsubscribeTokens $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokens = new LifecycleEmailUnsubscribeTokens(new ConfigRepository(['app' => ['key' => 'test-key']]));
    }

    public function test_a_token_resolves_back_to_its_user(): void
    {
        $this->assertSame(42, $this->tokens->userIdFrom($this->tokens->tokenFor(42)));
    }

    public function test_a_token_cannot_be_moved_to_another_user(): void
    {
        [, $signature] = explode('.', $this->tokens->tokenFor(42));

        $this->assertNull($this->tokens->userIdFrom('43.'.$signature));
    }

    public function test_malformed_tokens_are_rejected(): void
    {
        foreach (['', '42', '42.', 'abc.def', '42.'.str_repeat('z', 64), '-1.'.str_repeat('a', 64)] as $token) {
            $this->assertNull($this->tokens->userIdFrom($token), $token);
        }
    }

    public function test_tokens_signed_with_another_key_are_rejected(): void
    {
        $other = new LifecycleEmailUnsubscribeTokens(new ConfigRepository(['app' => ['key' => 'other-key']]));

        $this->assertNull($this->tokens->userIdFrom($other->tokenFor(42)));
    }
}
