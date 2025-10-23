<?php

namespace Tests\Unit\Services\Infrastructure\Stripe;

use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeClientFactoryTest extends TestCase
{
    private StripeClientFactory $factory;
    private StripeConfigurationService $mockConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfigService = Mockery::mock(StripeConfigurationService::class);
        $this->factory = new StripeClientFactory($this->mockConfigService);
    }

    public function test_create_for_platform_creates_client_with_default_key(): void
    {
        $this->mockConfigService
            ->shouldReceive('getSecretKey')
            ->with(null)
            ->once()
            ->andReturn('sk_test_default');

        $client = $this->factory->createForPlatform();

        $this->assertInstanceOf(StripeClient::class, $client);
    }

    public function test_create_for_platform_creates_client_with_canada_key(): void
    {
        $this->mockConfigService
            ->shouldReceive('getSecretKey')
            ->with(StripePlatform::CANADA)
            ->once()
            ->andReturn('sk_test_canada');

        $client = $this->factory->createForPlatform(StripePlatform::CANADA);

        $this->assertInstanceOf(StripeClient::class, $client);
    }

    public function test_create_for_platform_creates_client_with_ireland_key(): void
    {
        $this->mockConfigService
            ->shouldReceive('getSecretKey')
            ->with(StripePlatform::IRELAND)
            ->once()
            ->andReturn('sk_test_ireland');

        $client = $this->factory->createForPlatform(StripePlatform::IRELAND);

        $this->assertInstanceOf(StripeClient::class, $client);
    }

    public function test_create_for_platform_throws_exception_when_no_secret_key(): void
    {
        $this->mockConfigService
            ->shouldReceive('getSecretKey')
            ->with(null)
            ->once()
            ->andReturn('');

        $this->expectException(StripeClientConfigurationException::class);
        $this->expectExceptionMessage('Stripe secret key not configured for platform: default');

        $this->factory->createForPlatform();
    }

    public function test_create_for_platform_throws_exception_for_canada_platform_missing_key(): void
    {
        $this->mockConfigService
            ->shouldReceive('getSecretKey')
            ->with(StripePlatform::CANADA)
            ->once()
            ->andReturn('');

        $this->expectException(StripeClientConfigurationException::class);
        $this->expectExceptionMessage('Stripe secret key not configured for platform: ca');

        $this->factory->createForPlatform(StripePlatform::CANADA);
    }

    public function test_create_for_platform_throws_exception_for_ireland_platform_missing_key(): void
    {
        $this->mockConfigService
            ->shouldReceive('getSecretKey')
            ->with(StripePlatform::IRELAND)
            ->once()
            ->andReturn('');

        $this->expectException(StripeClientConfigurationException::class);
        $this->expectExceptionMessage('Stripe secret key not configured for platform: ie');

        $this->factory->createForPlatform(StripePlatform::IRELAND);
    }
}