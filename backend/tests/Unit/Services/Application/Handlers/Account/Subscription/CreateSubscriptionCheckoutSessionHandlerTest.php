<?php

namespace Tests\Unit\Services\Application\Handlers\Account\Subscription;

use Exception;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\DomainObjects\Enums\SubscriptionBillingInterval;
use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Subscription\CreateSubscriptionCheckoutSessionHandler;
use HiEvents\Services\Application\Handlers\Account\Subscription\DTO\CreateSubscriptionCheckoutSessionDTO;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionSyncService;
use HiEvents\Services\Domain\Subscription\DTO\SubscriptionPriceDTO;
use HiEvents\Services\Domain\Subscription\SubscriptionPriceService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\Checkout\CheckoutServiceFactory;
use Stripe\Service\Checkout\SessionService;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;
use Tests\TestCase;

class CreateSubscriptionCheckoutSessionHandlerTest extends TestCase
{
    private AccountSubscriptionAccessService|MockInterface $accessService;

    private AccountSubscriptionSyncService|MockInterface $syncService;

    private AccountSubscriptionRepositoryInterface|MockInterface $subscriptionRepository;

    private AccountDeletionService|MockInterface $deletionService;

    private SubscriptionPriceService|MockInterface $priceService;

    private CustomerService|MockInterface $customers;

    private SessionService|MockInterface $sessions;

    private CreateSubscriptionCheckoutSessionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessService = Mockery::mock(AccountSubscriptionAccessService::class);
        $this->accessService->shouldReceive('assertSubscriptionsAvailable')->byDefault();
        $this->syncService = Mockery::mock(AccountSubscriptionSyncService::class);
        $this->subscriptionRepository = Mockery::mock(AccountSubscriptionRepositoryInterface::class);
        $this->deletionService = Mockery::mock(AccountDeletionService::class);
        $this->deletionService->shouldReceive('isAccountPendingDeletion')->andReturnFalse()->byDefault();
        $this->priceService = Mockery::mock(SubscriptionPriceService::class);
        $this->customers = Mockery::mock(CustomerService::class);
        $this->sessions = Mockery::mock(SessionService::class);

        $accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $accountRepository->shouldReceive('findById')->with(1)->andReturn(
            (new AccountDomainObject)->setId(1)->setName('Acme Events')->setEmail('owner@acme.test')->setCurrencyCode('USD')
        );

        $checkout = new CheckoutServiceFactory(null);
        (fn () => $this->services['sessions'] = func_get_arg(0))->call($checkout, $this->sessions);

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('customers')->andReturn($this->customers);
        $stripeClient->shouldReceive('getService')->with('checkout')->andReturn($checkout);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForBilling')->andReturn($stripeClient);

        $this->handler = new CreateSubscriptionCheckoutSessionHandler(
            $this->accessService,
            $this->syncService,
            $this->subscriptionRepository,
            $accountRepository,
            $this->deletionService,
            $stripeClientFactory,
            $this->priceService,
        );
    }

    public function test_creates_a_customer_and_a_checkout_session_in_the_accounts_currency(): void
    {
        $this->syncService->shouldReceive('refreshForAccount')->with(1)->andReturnNull();
        $this->subscriptionRepository->shouldReceive('findFirstWhere')->with(['account_id' => 1])->andReturnNull();
        $this->customers->shouldReceive('create')
            ->once()
            ->with(
                ['email' => 'owner@acme.test', 'name' => 'Acme Events', 'metadata' => ['account_id' => '1']],
                ['idempotency_key' => 'account-subscription-customer-1'],
            )
            ->andReturn(Customer::constructFrom(['id' => 'cus_new']));
        $this->subscriptionRepository->shouldReceive('create')
            ->once()
            ->with(['account_id' => 1, 'stripe_customer_id' => 'cus_new']);
        $this->givenCustomerCurrency('cus_new', null);
        $this->givenPrices('USD');

        $this->sessions->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params) => $params['mode'] === 'subscription'
                && $params['customer'] === 'cus_new'
                && $params['currency'] === 'usd'
                && $params['line_items'] === [['price' => 'price_yearly', 'quantity' => 1]]
                && $params['automatic_tax'] === ['enabled' => true]
                && str_ends_with($params['success_url'], '/settings/billing?checkout=success'))
            ->andReturn(Session::constructFrom(['url' => 'https://checkout.stripe.com/c/cs_1']));

        $this->assertSame(
            'https://checkout.stripe.com/c/cs_1',
            $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::YEAR)),
        );
    }

    public function test_a_returning_customer_is_charged_in_the_currency_stripe_locked_them_to(): void
    {
        $this->syncService->shouldReceive('refreshForAccount')->andReturn($this->accountSubscription(AccountSubscriptionStatus::CANCELED));
        $this->subscriptionRepository->shouldReceive('findFirstWhere')->andReturn($this->accountSubscription(AccountSubscriptionStatus::CANCELED));
        $this->customers->shouldNotReceive('create');
        $this->givenCustomerCurrency('cus_existing', 'eur');
        $this->givenPrices('EUR');

        $this->sessions->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params) => $params['customer'] === 'cus_existing' && $params['currency'] === 'eur')
            ->andReturn(Session::constructFrom(['url' => 'https://checkout.stripe.com/c/cs_1']));

        $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::YEAR));
    }

    public function test_a_concurrent_first_checkout_reuses_the_customer_the_other_request_stored(): void
    {
        $this->syncService->shouldReceive('refreshForAccount')->andReturnNull();
        $this->subscriptionRepository->shouldReceive('findFirstWhere')
            ->andReturn(null, (new AccountSubscriptionDomainObject)->setStripeCustomerId('cus_winner'));
        $this->customers->shouldReceive('create')->andReturn(Customer::constructFrom(['id' => 'cus_winner']));
        $this->subscriptionRepository->shouldReceive('create')
            ->andThrow(new UniqueConstraintViolationException('pgsql', 'insert', [], new Exception('duplicate key')));
        $this->givenCustomerCurrency('cus_winner', null);
        $this->givenPrices('USD');

        $this->sessions->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params) => $params['customer'] === 'cus_winner')
            ->andReturn(Session::constructFrom(['url' => 'https://checkout.stripe.com/c/cs_1']));

        $this->assertSame(
            'https://checkout.stripe.com/c/cs_1',
            $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::YEAR)),
        );
    }

    public function test_a_customer_deleted_in_stripe_is_replaced_instead_of_blocking_checkout(): void
    {
        $this->syncService->shouldReceive('refreshForAccount')
            ->andThrow(InvalidRequestException::factory('No such customer', 404, null, null, null, 'resource_missing'));
        $this->subscriptionRepository->shouldReceive('deleteWhere')->with(['account_id' => 1])->once();
        $this->subscriptionRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->customers->shouldReceive('create')->once()->andReturn(Customer::constructFrom(['id' => 'cus_fresh']));
        $this->subscriptionRepository->shouldReceive('create')->once();
        $this->givenCustomerCurrency('cus_fresh', null);
        $this->givenPrices('USD');

        $this->sessions->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params) => $params['customer'] === 'cus_fresh')
            ->andReturn(Session::constructFrom(['url' => 'https://checkout.stripe.com/c/cs_1']));

        $this->assertSame(
            'https://checkout.stripe.com/c/cs_1',
            $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::YEAR)),
        );
    }

    #[DataProvider('openStatuses')]
    public function test_refuses_while_a_subscription_is_still_open(AccountSubscriptionStatus $status): void
    {
        $this->syncService->shouldReceive('refreshForAccount')->with(1)->andReturn($this->accountSubscription($status));

        $this->sessions->shouldNotReceive('create');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::MONTH));
    }

    public static function openStatuses(): array
    {
        return [
            'active' => [AccountSubscriptionStatus::ACTIVE],
            'payment still processing' => [AccountSubscriptionStatus::INCOMPLETE],
            'unpaid' => [AccountSubscriptionStatus::UNPAID],
            'past due' => [AccountSubscriptionStatus::PAST_DUE],
        ];
    }

    public function test_refuses_while_the_account_is_pending_deletion(): void
    {
        $this->deletionService->shouldReceive('isAccountPendingDeletion')->with(1)->andReturnTrue();

        $this->syncService->shouldNotReceive('refreshForAccount');
        $this->sessions->shouldNotReceive('create');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::MONTH));
    }

    public function test_refuses_off_cloud(): void
    {
        $this->accessService->shouldReceive('assertSubscriptionsAvailable')->andThrow(new FeatureNotEnabledException);

        $this->sessions->shouldNotReceive('create');

        $this->expectException(FeatureNotEnabledException::class);

        $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::MONTH));
    }

    public function test_fails_clearly_when_the_interval_has_no_price(): void
    {
        $this->syncService->shouldReceive('refreshForAccount')->andReturnNull();
        $this->subscriptionRepository->shouldReceive('findFirstWhere')
            ->andReturn((new AccountSubscriptionDomainObject)->setStripeCustomerId('cus_existing'));
        $this->givenCustomerCurrency('cus_existing', null);
        $this->priceService->shouldReceive('getPrices')->andReturn([]);

        $this->sessions->shouldNotReceive('create');

        $this->expectException(StripeClientConfigurationException::class);

        $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(1, SubscriptionBillingInterval::MONTH));
    }

    private function givenCustomerCurrency(string $customerId, ?string $currency): void
    {
        $this->customers->shouldReceive('retrieve')
            ->with($customerId)
            ->andReturn(Customer::constructFrom(['id' => $customerId, 'currency' => $currency]));
    }

    private function givenPrices(string $currency): void
    {
        $this->priceService->shouldReceive('getPrices')->with($currency)->andReturn([
            new SubscriptionPriceDTO(SubscriptionBillingInterval::MONTH, 'price_monthly', 2999, $currency),
            new SubscriptionPriceDTO(SubscriptionBillingInterval::YEAR, 'price_yearly', 29999, $currency),
        ]);
    }

    private function accountSubscription(AccountSubscriptionStatus $status): AccountSubscriptionDomainObject
    {
        return (new AccountSubscriptionDomainObject)
            ->setAccountId(1)
            ->setStripeCustomerId('cus_existing')
            ->setStripeSubscriptionId('sub_1')
            ->setStatus($status->name);
    }
}
