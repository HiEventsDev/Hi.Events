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

        $this->configMock = $this->createMock(Repository::class);

        $this->factory = new RazorpayClientFactory($this->configMock);
    }

    public function testItCreatesClientSuccessfullyWhenConfigured(): void
    {
        
        $this->configMock->method('get')->willReturnCallback(function (string $key) {
            if ($key === 'services.razorpay.key_id') {
                return 'test_key_id';
            }
            if ($key === 'services.razorpay.key_secret') {
                return 'test_key_secret';
            }
            return null;
        });
        
        $client = $this->factory->create();
        
        $this->assertInstanceOf(RazorpayApiClient::class, $client);
    }

    public function testItThrowsExceptionWhenKeyIdIsMissing(): void
    {
        $this->configMock->method('get')->willReturnCallback(function (string $key) {
            if ($key === 'services.razorpay.key_secret') {
                return 'test_key_secret';
            }
            return null; 
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Razorpay credentials not configured.');

        $this->factory->create();
    }

    public function testItThrowsExceptionWhenKeySecretIsMissing(): void
    {
        $this->configMock->method('get')->willReturnCallback(function (string $key) {
            if ($key === 'services.razorpay.key_id') {
                return 'test_key_id';
            }
            return ''; 
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Razorpay credentials not configured.');

        $this->factory->create();
    }
}