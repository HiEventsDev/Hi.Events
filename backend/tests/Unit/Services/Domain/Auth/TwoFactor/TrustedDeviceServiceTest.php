<?php

namespace Tests\Unit\Services\Domain\Auth\TwoFactor;

use HiEvents\Repository\Interfaces\UserTrustedDeviceRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use Mockery as m;
use Tests\TestCase;

class TrustedDeviceServiceTest extends TestCase
{
    private UserTrustedDeviceRepositoryInterface $repository;

    private TrustedDeviceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = m::mock(UserTrustedDeviceRepositoryInterface::class);
        $this->service = new TrustedDeviceService($this->repository);
    }

    public function test_trust_stores_a_hash_of_the_returned_token(): void
    {
        $created = null;
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $attributes) use (&$created) {
                $created = $attributes;

                return true;
            });

        $token = $this->service->trust(5, str_repeat('a', 600), '10.0.0.1');

        $this->assertSame(hash('sha256', $token), $created['token_hash']);
        $this->assertSame(5, $created['user_id']);
        $this->assertSame(512, strlen($created['user_agent']));
        $this->assertTrue($created['expires_at']->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_is_trusted_matches_user_token_and_expiry(): void
    {
        $this->repository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(fn (array $attributes, array $where) => $where['user_id'] === 5
                && $where['token_hash'] === hash('sha256', 'device-token')
                && $where[0][0] === 'expires_at')
            ->andReturn(1);

        $this->assertTrue($this->service->isTrusted(5, 'device-token'));
    }

    public function test_is_trusted_without_a_token_skips_the_lookup(): void
    {
        $this->repository->shouldNotReceive('updateWhere');

        $this->assertFalse($this->service->isTrusted(5, null));
        $this->assertFalse($this->service->isTrusted(5, ''));
    }

    public function test_unknown_token_is_not_trusted(): void
    {
        $this->repository->shouldReceive('updateWhere')->once()->andReturn(0);

        $this->assertFalse($this->service->isTrusted(5, 'stale'));
    }
}
