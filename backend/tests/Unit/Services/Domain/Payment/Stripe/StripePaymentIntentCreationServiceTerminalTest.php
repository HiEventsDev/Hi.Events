<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\StripeCustomerDomainObject;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Repository\Interfaces\StripeCustomerRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Psr\Log\NullLogger;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentIntentCreationServiceTerminalTest extends TestCase
{
    private StripeClient $client;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_terminal_intent_uses_card_present_without_a_customer_when_no_email(): void
    {
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('beginTransaction');
        $databaseManager->shouldReceive('commit');

        $customerRepository = Mockery::mock(StripeCustomerRepositoryInterface::class);
        $customerRepository->shouldNotReceive('findFirstWhere');

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params, array $opts) {
                return $params['payment_method_types'] === ['card_present']
                    && $params['capture_method'] === 'automatic'
                    && ! array_key_exists('customer', $params)
                    && ! array_key_exists('automatic_payment_methods', $params)
                    && $params['metadata']['box_office_id'] === 12
                    && $params['amount'] === 2500
                    && $opts === [];
            })
            ->andReturn(PaymentIntent::constructFrom(['id' => 'pi_terminal', 'client_secret' => 'secret']));

        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $paymentIntents;

        $service = new StripePaymentIntentCreationService(
            logger: new NullLogger,
            config: new Repository(['app' => ['saas_mode_enabled' => false]]),
            stripeCustomerRepository: $customerRepository,
            databaseManager: $databaseManager,
            orderApplicationFeeCalculationService: Mockery::mock(OrderApplicationFeeCalculationService::class),
        );

        $order = (new OrderDomainObject)->setId(1)->setEventId(2)->setShortId('o_1')->setBoxOfficeId(12)->setEmail(null);

        $response = $service->createTerminalPaymentIntentWithClient($client, new CreatePaymentIntentRequestDTO(
            amount: MoneyValue::fromFloat(25.0, 'USD'),
            currencyCode: 'USD',
            account: (new AccountDomainObject)->setId(9),
            order: $order,
        ));

        $this->assertSame('pi_terminal', $response->paymentIntentId);
    }

    public function test_a_terminal_intent_stripe_rejects_reports_stripes_reason_to_the_door(): void
    {
        $service = $this->serviceRejectingWith('The card_present source type with currency usd is not supported in IE.');

        try {
            $service->createTerminalPaymentIntentWithClient($this->client, $this->terminalRequest());
            $this->fail('Expected the payment intent creation to fail');
        } catch (CreatePaymentIntentFailedException $exception) {
            $this->assertStringContainsString('The card_present source type with currency usd is not supported in IE.', $exception->getMessage());
        }
    }

    public function test_an_online_intent_stripe_rejects_keeps_the_generic_buyer_message(): void
    {
        $service = $this->serviceRejectingWith('Some internal Stripe detail');

        try {
            $service->createPaymentIntentWithClient($this->client, $this->terminalRequest());
            $this->fail('Expected the payment intent creation to fail');
        } catch (CreatePaymentIntentFailedException $exception) {
            $this->assertStringNotContainsString('Some internal Stripe detail', $exception->getMessage());
        }
    }

    private function serviceRejectingWith(string $stripeMessage): StripePaymentIntentCreationService
    {
        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('beginTransaction');
        $databaseManager->shouldReceive('rollBack');

        $customerRepository = Mockery::mock(StripeCustomerRepositoryInterface::class);
        $customerRepository->shouldReceive('findFirstWhere')->andReturn(
            (new StripeCustomerDomainObject)->setId(3)->setStripeCustomerId('cus_1')->setName('Door Buyer'),
        );

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('create')->andThrow(InvalidRequestException::factory($stripeMessage));

        $this->client = Mockery::mock(StripeClient::class);
        $this->client->paymentIntents = $paymentIntents;

        return new StripePaymentIntentCreationService(
            logger: new NullLogger,
            config: new Repository(['app' => ['saas_mode_enabled' => false]]),
            stripeCustomerRepository: $customerRepository,
            databaseManager: $databaseManager,
            orderApplicationFeeCalculationService: Mockery::mock(OrderApplicationFeeCalculationService::class),
        );
    }

    private function terminalRequest(): CreatePaymentIntentRequestDTO
    {
        return new CreatePaymentIntentRequestDTO(
            amount: MoneyValue::fromFloat(25.0, 'USD'),
            currencyCode: 'USD',
            account: (new AccountDomainObject)->setId(9),
            order: (new OrderDomainObject)->setId(1)->setEventId(2)->setShortId('o_1')->setBoxOfficeId(12)
                ->setEmail('buyer@example.com')->setFirstName('Door')->setLastName('Buyer'),
        );
    }
}
