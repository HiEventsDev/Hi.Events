<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use Illuminate\Config\Repository;
use Illuminate\Hashing\BcryptHasher;
use Tests\TestCase;

class BoxOfficePinServiceTest extends TestCase
{
    private BoxOfficePinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BoxOfficePinService(
            hasher: new BcryptHasher(['rounds' => 4]),
            config: new Repository(['app' => ['key' => 'base64:testkeytestkeytestkeytestkey']]),
        );
    }

    public function test_generates_a_six_digit_pin(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $this->service->generate());
        }
    }

    public function test_verifies_the_pin_it_hashed(): void
    {
        $hash = $this->service->hash('1234');

        $this->assertTrue($this->service->verify('1234', $hash));
        $this->assertFalse($this->service->verify('4321', $hash));
    }

    public function test_hash_is_keyed_by_app_key(): void
    {
        $otherService = new BoxOfficePinService(
            hasher: new BcryptHasher(['rounds' => 4]),
            config: new Repository(['app' => ['key' => 'base64:anotherkeyanotherkeyanotherkey']]),
        );

        $hash = $this->service->hash('1234');

        $this->assertFalse($otherService->verify('1234', $hash));
    }
}
