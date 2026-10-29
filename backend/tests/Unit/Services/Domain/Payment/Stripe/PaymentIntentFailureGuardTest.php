<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Services\Domain\Payment\Stripe\PaymentIntentFailureGuard;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;
use Tests\TestCase;

class PaymentIntentFailureGuardTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private PaymentIntentService|MockInterface $paymentIntents;

    private PaymentIntentFailureGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentIntents = Mockery::mock(PaymentIntentService::class);

        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $this->paymentIntents;

        $factory = Mockery::mock(StripeClientFactory::class);
        $factory->shouldReceive('createForPlatform')->andReturn($client);

        $this->guard = new PaymentIntentFailureGuard(new Repository(new ArrayStore), $factory, new NullLogger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_does_not_cancel_before_the_failure_limit(): void
    {
        $this->paymentIntents->shouldNotReceive('cancel');

        foreach (range(1, PaymentIntentFailureGuard::MAX_FAILED_ATTEMPTS - 1) as $ignored) {
            $this->guard->recordFailure($this->stripePayment());
        }
    }

    public function test_cancels_the_payment_intent_on_the_connected_account_once_the_limit_is_reached(): void
    {
        $this->paymentIntents
            ->shouldReceive('cancel')
            ->once()
            ->with('pi_123', [], ['stripe_account' => 'acct_1']);

        foreach (range(1, PaymentIntentFailureGuard::MAX_FAILED_ATTEMPTS + 2) as $ignored) {
            $this->guard->recordFailure($this->stripePayment());
        }
    }

    private function stripePayment(): StripePaymentDomainObject
    {
        return (new StripePaymentDomainObject)
            ->setOrderId(7)
            ->setPaymentIntentId('pi_123')
            ->setConnectedAccountId('acct_1');
    }
}
