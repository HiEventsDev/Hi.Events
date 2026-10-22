<?php

namespace Tests\Unit\Services\Application\Handlers\Account\Subscription;

use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Subscription\CreateSubscriptionPortalSessionHandler;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Mockery;
use Mockery\MockInterface;
use Stripe\BillingPortal\Session;
use Stripe\Service\BillingPortal\BillingPortalServiceFactory;
use Stripe\Service\BillingPortal\SessionService;
use Stripe\StripeClient;
use Tests\TestCase;

class CreateSubscriptionPortalSessionHandlerTest extends TestCase
{
    private AccountSubscriptionRepositoryInterface|MockInterface $repository;

    private SessionService|MockInterface $sessions;

    private CreateSubscriptionPortalSessionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(AccountSubscriptionRepositoryInterface::class);
        $this->sessions = Mockery::mock(SessionService::class);

        $billingPortal = new BillingPortalServiceFactory(null);
        (fn () => $this->services['sessions'] = func_get_arg(0))->call($billingPortal, $this->sessions);

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('billingPortal')->andReturn($billingPortal);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForBilling')->andReturn($stripeClient);

        $accessService = Mockery::mock(AccountSubscriptionAccessService::class);
        $accessService->shouldReceive('assertSubscriptionsAvailable');

        $this->handler = new CreateSubscriptionPortalSessionHandler($accessService, $this->repository, $stripeClientFactory);
    }

    public function test_opens_the_portal_for_the_accounts_customer(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->with(['account_id' => 1])->andReturn(
            (new AccountSubscriptionDomainObject)->setStripeCustomerId('cus_1')->setStripeSubscriptionId('sub_1')
        );

        $this->sessions->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params) => $params['customer'] === 'cus_1'
                && str_ends_with($params['return_url'], '/settings/billing'))
            ->andReturn(Session::constructFrom(['url' => 'https://billing.stripe.com/p/session_1']));

        $this->assertSame('https://billing.stripe.com/p/session_1', $this->handler->handle(1));
    }

    public function test_accounts_that_never_subscribed_have_no_portal(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->andReturn(
            (new AccountSubscriptionDomainObject)->setStripeCustomerId('cus_1')->setStripeSubscriptionId(null)
        );

        $this->sessions->shouldNotReceive('create');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(1);
    }
}
