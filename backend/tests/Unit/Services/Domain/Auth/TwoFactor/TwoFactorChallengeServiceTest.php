<?php

namespace Tests\Unit\Services\Domain\Auth\TwoFactor;

use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorChallengeService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\TestCase;

class TwoFactorChallengeServiceTest extends TestCase
{
    private TwoFactorChallengeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TwoFactorChallengeService(new Repository(new ArrayStore));
    }

    public function test_create_and_find(): void
    {
        $token = $this->service->create(12);

        $challenge = $this->service->find($token);

        $this->assertSame(12, $challenge->userId);
        $this->assertFalse($challenge->verified);
        $this->assertNull($this->service->find('unknown'));
    }

    public function test_forget_removes_the_challenge(): void
    {
        $token = $this->service->create(12);

        $this->service->forget($token);

        $this->assertNull($this->service->find($token));
    }

    public function test_mark_verified_keeps_the_recovery_code_count(): void
    {
        $token = $this->service->create(12);

        $this->service->markVerified($token, $this->service->find($token), 3);

        $challenge = $this->service->find($token);
        $this->assertTrue($challenge->verified);
        $this->assertSame(3, $challenge->recoveryCodesRemaining);
    }
}
