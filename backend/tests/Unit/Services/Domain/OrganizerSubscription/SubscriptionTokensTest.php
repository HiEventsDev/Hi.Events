<?php

namespace Tests\Unit\Services\Domain\OrganizerSubscription;

use HiEvents\Services\Domain\OrganizerSubscription\SubscriptionTokens;
use Tests\TestCase;

class SubscriptionTokensTest extends TestCase
{
    public function test_tokens_are_scoped_signed_and_tamper_resistant(): void
    {
        config(['app.key' => 'test-secret']);
        $tokens = new SubscriptionTokens;
        $token = $tokens->unsubscribeToken(42);
        $this->assertSame(42, $tokens->subscriptionId($token));
        $this->assertNull($tokens->subscriptionId('43.'.explode('.', $token)[1]));
        $this->assertNull($tokens->subscriptionId('invalid'));
        config(['app.key' => 'another-secret']);
        $this->assertNull($tokens->subscriptionId($token));
    }
}
