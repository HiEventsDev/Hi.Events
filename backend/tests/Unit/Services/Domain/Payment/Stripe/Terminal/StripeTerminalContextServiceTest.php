<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\Terminal;

use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalContextService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeTerminalContextServiceTest extends TestCase
{
    private MockInterface|StripeClientFactory $clientFactory;

    private MockInterface|StripeConfigurationService $configurationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientFactory = Mockery::mock(StripeClientFactory::class);
        $this->configurationService = Mockery::mock(StripeConfigurationService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(bool $saas): StripeTerminalContextService
    {
        return new StripeTerminalContextService(
            stripeClientFactory: $this->clientFactory,
            stripeConfigurationService: $this->configurationService,
            config: new Repository(['app' => ['saas_mode_enabled' => $saas]]),
        );
    }

    public function test_non_saas_uses_the_platform_account(): void
    {
        $client = new StripeClient('sk_test_x');
        $this->configurationService->shouldReceive('getPrimaryPlatform')->andReturn(StripePlatform::IRELAND);
        $this->clientFactory->shouldReceive('createForPlatform')->with(StripePlatform::IRELAND)->andReturn($client);

        $context = $this->service(false)->forOrganizer(new OrganizerDomainObject);

        $this->assertSame($client, $context->client);
        $this->assertNull($context->stripe_account_id);
        $this->assertSame([], $context->requestOptions());
    }

    public function test_saas_requires_a_completed_connect_account(): void
    {
        $this->expectException(ResourceConflictException::class);

        $this->service(true)->forOrganizer(new OrganizerDomainObject);
    }

    public function test_saas_uses_the_connected_account(): void
    {
        $platform = (new OrganizerStripePlatformDomainObject)
            ->setId(4)
            ->setStripeAccountId('acct_123')
            ->setStripeConnectPlatform('ie')
            ->setStripeSetupCompletedAt(now()->toDateTimeString())
            ->setCreatedAt(now()->toDateTimeString());
        $organizer = (new OrganizerDomainObject)->setOrganizerStripePlatforms(collect([$platform]));
        $client = new StripeClient('sk_test_x');
        $this->clientFactory->shouldReceive('createForPlatform')->with(StripePlatform::IRELAND)->andReturn($client);

        $context = $this->service(true)->forOrganizer($organizer);

        $this->assertSame('acct_123', $context->stripe_account_id);
        $this->assertSame(4, $context->organizer_stripe_platform_id);
        $this->assertSame(['stripe_account' => 'acct_123'], $context->requestOptions());
    }
}
