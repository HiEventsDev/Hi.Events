<?php

namespace Tests\Unit\Services\Infrastructure\Razorpay;

use HiEvents\Services\Infrastructure\Razorpay\RazorpayApiClient;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use Illuminate\Config\Repository;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Tests\TestCase;

class RazorpayClientFactoryTest extends TestCase
{
    private Repository&MockObject $configMock;
    private RazorpayClientFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Mock Laravel's config repository
        $this->configMock = $this->createMock(Repository::class);

        // 2. Inject it into the factory
        $this->factory = new RazorpayClientFactory($this->configMock);
    }

    public function testItCreatesClientSuccessfullyWhenConfigured(): void
    {
        // Tell the mock exactly how to respond when ->get() is called
        $this->configMock->method('get')->willReturnCallback(function (string $key) {
            if ($key === 'services.razorpay.key_id') {
                return 'test_key_id';
            }
            if ($key === 'services.razorpay.key_secret') {
                return 'test_key_secret';
            }
            return null;
        });

        // Act
        $client = $this->factory->create();

        // Assert that the factory successfully built the client
        $this->assertInstanceOf(RazorpayApiClient::class, $client);
    }

    public function testItThrowsExceptionWhenKeyIdIsMissing(): void
    {
        // Simulate missing Key ID but present Key Secret
        $this->configMock->method('get')->willReturnCallback(function (string $key) {
            if ($key === 'services.razorpay.key_secret') {
                return 'test_key_secret';
            }
            return null; // The Key ID will return null
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Razorpay credentials not configured.');

        $this->factory->create();
    }

    public function testItThrowsExceptionWhenKeySecretIsMissing(): void
    {
        // Simulate present Key ID but missing Key Secret
        $this->configMock->method('get')->willReturnCallback(function (string $key) {
            if ($key === 'services.razorpay.key_id') {
                return 'test_key_id';
            }
            return ''; // Testing that an empty string also triggers the failure
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Razorpay credentials not configured.');

        $this->factory->create();
    }
}