<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use Mockery;
use Stripe\Refund;
use Stripe\Service\RefundService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentIntentRefundServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_idempotency_key_is_derived_from_the_payment_intent_not_the_local_row_id(): void
    {
        $payment = (new StripePaymentDomainObject)
            ->setId(1)
            ->setPaymentIntentId('pi_test_abc123');

        $refunds = Mockery::mock(RefundService::class);
        $refunds->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params, array $opts) {
                return $params['payment_intent'] === 'pi_test_abc123'
                    && $params['amount'] === 1250
                    && $opts['idempotency_key'] === 'refund_pi_test_abc123_amount_1250';
            })
            ->andReturn(Refund::constructFrom(['id' => 're_test']));

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('refunds')->andReturn($refunds);

        $service = new StripePaymentIntentRefundService(
            new Repository(['app' => ['saas_mode_enabled' => false]]),
        );

        $refund = $service->refundPayment(
            MoneyValue::fromFloat(12.50, 'USD'),
            $payment,
            $stripeClient,
        );

        $this->assertSame('re_test', $refund->id);
    }
}
