<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer\Payment\Stripe;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerStripePlatformRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DisconnectStripeConnectAccountHandler;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class DisconnectStripeConnectAccountHandlerTest extends TestCase
{
    private OrganizerRepositoryInterface $organizerRepository;

    private OrganizerStripePlatformRepositoryInterface $organizerStripePlatformRepository;

    private LoggerInterface $logger;

    private DisconnectStripeConnectAccountHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->organizerStripePlatformRepository = m::mock(OrganizerStripePlatformRepositoryInterface::class);
        $this->logger = m::mock(LoggerInterface::class);

        $this->handler = new DisconnectStripeConnectAccountHandler(
            $this->organizerRepository,
            $this->organizerStripePlatformRepository,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_soft_deletes_matching_stripe_platform_rows(): void
    {
        $this->expectNotToPerformAssertions();

        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 1, 'account_id' => 99])
            ->andReturn((new OrganizerDomainObject)->setId(1)->setAccountId(99));

        $this->organizerStripePlatformRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with(['organizer_id' => 1, 'stripe_account_id' => 'acct_123'])
            ->andReturn(1);

        $this->logger->shouldReceive('info')->once();

        $this->handler->handle(organizerId: 1, accountId: 99, stripeAccountId: 'acct_123');
    }

    public function test_throws_when_organizer_not_found(): void
    {
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 1, 'account_id' => 99])
            ->andReturnNull();

        $this->organizerStripePlatformRepository->shouldNotReceive('deleteWhere');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(organizerId: 1, accountId: 99, stripeAccountId: 'acct_123');
    }

    public function test_throws_when_no_connection_matches(): void
    {
        $this->organizerRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn((new OrganizerDomainObject)->setId(1)->setAccountId(99));

        $this->organizerStripePlatformRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with(['organizer_id' => 1, 'stripe_account_id' => 'acct_missing'])
            ->andReturn(0);

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(organizerId: 1, accountId: 99, stripeAccountId: 'acct_missing');
    }
}
