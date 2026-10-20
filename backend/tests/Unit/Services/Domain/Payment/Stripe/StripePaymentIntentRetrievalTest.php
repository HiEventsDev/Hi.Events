<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Repository\Interfaces\StripeCustomerRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Psr\Log\NullLogger;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentIntentRetrievalTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_the_client_secret_of_a_usable_payment_intent(): void
    {
        $secret = $this->service()->retrievePaymentIntentClientSecretWithClient(
            $this->clientReturning(PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD),
            'pi_1',
        );

        $this->assertSame('pi_1_secret', $secret);
    }

    public function test_refuses_a_cancelled_payment_intent_and_asks_for_a_new_order(): void
    {
        $this->expectException(CreatePaymentIntentFailedException::class);
        $this->expectExceptionMessage('This payment was cancelled after too many failed attempts. Please start a new order.');

        $this->service()->retrievePaymentIntentClientSecretWithClient(
            $this->clientReturning(PaymentIntent::STATUS_CANCELED),
            'pi_1',
        );
    }

    private function clientReturning(string $status): StripeClient
    {
        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('retrieve')->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => $status,
            'client_secret' => 'pi_1_secret',
        ]));

        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $paymentIntents;

        return $client;
    }

    private function service(): StripePaymentIntentCreationService
    {
        return new StripePaymentIntentCreationService(
            logger: new NullLogger,
            config: new Repository([]),
            stripeCustomerRepository: Mockery::mock(StripeCustomerRepositoryInterface::class),
            databaseManager: Mockery::mock(DatabaseManager::class),
            orderApplicationFeeCalculationService: Mockery::mock(OrderApplicationFeeCalculationService::class),
        );
    }
}
