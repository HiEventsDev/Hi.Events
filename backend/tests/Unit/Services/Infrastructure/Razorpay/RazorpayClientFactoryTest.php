<?php

namespace Tests\Unit\Services\Infrastructure\Razorpay;

use HiEvents\Services\Infrastructure\Razorpay\RazorpayApiClient;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientInterface;
use Illuminate\Config\Repository;
use Tests\TestCase;

class RazorpayClientFactoryTest extends TestCase
{
    private function makeConfig(array $values): Repository
    {
        return new Repository([
            'services' => [
                'razorpay' => $values
            ]
        ]);
    }

    public function test_creates_client_when_credentials_exists(){
        $config = $this->makeConfig([
            'key_id' => 'test_id',
            'key_secret' => 'test_secret'
        ]);

        $factory = new RazorpayClientFactory($config);
        $client = $factory->create();

        $this->assertInstanceOf(RazorpayClientInterface::class, $client);
        $this->assertInstanceOf(RazorpayApiClient::class, $client);
    }

    public function test_throws_exception_when_key_id_missing()
    {
        $config = $this->makeConfig([
            'key_secret' => 'test_secret',
        ]);

        $factory = new RazorpayClientFactory($config);

        $this->expectException(\RuntimeException::class);

        $factory->create();
    }

    public function test_throws_exception_when_key_secret_missing()
    {
        $config = $this->makeConfig([
            'key_id' => 'test_key',
        ]);

        $factory = new RazorpayClientFactory($config);

        $this->expectException(\RuntimeException::class);

        $factory->create();
    }
}