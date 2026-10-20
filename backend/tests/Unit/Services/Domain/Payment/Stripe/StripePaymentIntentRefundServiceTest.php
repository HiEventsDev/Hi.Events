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
    private array $requests = [];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_idempotency_key_is_derived_from_the_payment_intent_not_the_local_row_id(): void
    {
        $refund = $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 0);

        $this->assertSame('re_test', $refund->id);
        $this->assertSame('pi_test_abc123', $this->requests[0]['params']['payment_intent']);
        $this->assertSame(1250, $this->requests[0]['params']['amount']);
        $this->assertSame('refund_pi_test_abc123_amount_1250', $this->requests[0]['opts']['idempotency_key']);
    }

    public function test_a_second_refund_of_the_same_amount_gets_a_new_idempotency_key_once_the_first_is_recorded(): void
    {
        $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 0);
        $this->refund(amount: 12.50, alreadyRefunded: 12.50, failedRefundCount: 0);

        $this->assertNotSame($this->requests[0]['opts']['idempotency_key'], $this->requests[1]['opts']['idempotency_key']);
        $this->assertSame($this->requests[0]['params'], $this->requests[1]['params']);
    }

    public function test_retrying_a_refund_that_failed_gets_a_new_idempotency_key(): void
    {
        $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 0);
        $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 1);
        $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 2);

        $keys = array_map(fn (array $request) => $request['opts']['idempotency_key'], $this->requests);

        $this->assertCount(3, array_unique($keys));
    }

    public function test_retrying_the_same_refund_reuses_the_idempotency_key(): void
    {
        $this->refund(amount: 12.50, alreadyRefunded: 20.0, failedRefundCount: 1);
        $this->refund(amount: 12.50, alreadyRefunded: 20.0, failedRefundCount: 1);

        $this->assertSame($this->requests[0]['opts']['idempotency_key'], $this->requests[1]['opts']['idempotency_key']);
        $this->assertSame($this->requests[0]['params'], $this->requests[1]['params']);
    }

    public function test_the_buyer_notification_request_travels_with_the_refund_as_metadata(): void
    {
        $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 0, notifyBuyer: true);

        $this->assertSame(
            [StripePaymentIntentRefundService::NOTIFY_BUYER_METADATA_KEY => '1'],
            $this->requests[0]['params']['metadata'],
        );
    }

    public function test_no_metadata_is_sent_when_the_buyer_is_not_to_be_notified(): void
    {
        $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 0);

        $this->assertArrayNotHasKey('metadata', $this->requests[0]['params']);
    }

    public function test_the_refund_is_created_on_the_connected_account_in_saas_mode(): void
    {
        $this->refund(amount: 12.50, alreadyRefunded: 0.0, failedRefundCount: 0, saasMode: true);

        $this->assertSame('acct_test', $this->requests[0]['opts']['stripe_account']);
    }

    private function refund(
        float $amount,
        float $alreadyRefunded,
        int $failedRefundCount,
        bool $notifyBuyer = false,
        bool $saasMode = false,
    ): Refund {
        $payment = (new StripePaymentDomainObject)
            ->setId(1)
            ->setPaymentIntentId('pi_test_abc123')
            ->setConnectedAccountId('acct_test');

        $refunds = Mockery::mock(RefundService::class);
        $refunds->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $params, array $opts) {
                $this->requests[] = ['params' => $params, 'opts' => $opts];

                return Refund::constructFrom(['id' => 're_test']);
            });

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('refunds')->andReturn($refunds);

        $service = new StripePaymentIntentRefundService(
            new Repository(['app' => ['saas_mode_enabled' => $saasMode]]),
        );

        return $service->refundPayment(
            amount: MoneyValue::fromFloat($amount, 'USD'),
            alreadyRefunded: MoneyValue::fromFloat($alreadyRefunded, 'USD'),
            failedRefundCount: $failedRefundCount,
            payment: $payment,
            stripeClient: $stripeClient,
            notifyBuyer: $notifyBuyer,
        );
    }
}
