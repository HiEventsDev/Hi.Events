<?php

namespace Tests\Unit\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use HiEvents\Services\Domain\Auth\TwoFactor\RecoveryCodeService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorVerifier;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Mockery as m;
use Tests\TestCase;

class TwoFactorVerifierTest extends TestCase
{
    private TwoFactorAuthenticationService $twoFactorAuthenticationService;

    private RecoveryCodeService $recoveryCodeService;

    private TwoFactorVerifier $verifier;

    private UserDomainObject $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->twoFactorAuthenticationService = m::mock(TwoFactorAuthenticationService::class);
        $this->recoveryCodeService = m::mock(RecoveryCodeService::class);
        $this->verifier = new TwoFactorVerifier(
            $this->twoFactorAuthenticationService,
            $this->recoveryCodeService,
            new RateLimiter(new Repository(new ArrayStore)),
        );
        $this->user = (new UserDomainObject)->setId(5);
    }

    public function test_uses_the_recovery_code_when_one_is_given(): void
    {
        $this->recoveryCodeService->shouldReceive('consume')->once()->with($this->user, 'abcde-fghjk')->andReturn(true);
        $this->twoFactorAuthenticationService->shouldNotReceive('verifyCode');

        $this->assertTrue($this->verifier->verify($this->user, null, 'abcde-fghjk'));
    }

    public function test_locks_the_user_out_after_too_many_failures(): void
    {
        $this->twoFactorAuthenticationService->shouldReceive('verifyCode')
            ->times(TwoFactorVerifier::MAX_FAILED_ATTEMPTS)
            ->andReturn(false);

        for ($i = 0; $i < TwoFactorVerifier::MAX_FAILED_ATTEMPTS; $i++) {
            $this->assertFalse($this->verifier->verify($this->user, '000000'));
        }

        $this->expectException(TwoFactorLockedOutException::class);

        $this->verifier->verify($this->user, '123456');
    }

    public function test_a_success_resets_the_failure_count(): void
    {
        $this->twoFactorAuthenticationService->shouldReceive('verifyCode')->with($this->user, '000000')->andReturn(false);
        $this->twoFactorAuthenticationService->shouldReceive('verifyCode')->with($this->user, '123456')->andReturn(true);

        for ($i = 0; $i < TwoFactorVerifier::MAX_FAILED_ATTEMPTS - 1; $i++) {
            $this->verifier->verify($this->user, '000000');
        }
        $this->assertTrue($this->verifier->verify($this->user, '123456'));

        for ($i = 0; $i < TwoFactorVerifier::MAX_FAILED_ATTEMPTS - 1; $i++) {
            $this->assertFalse($this->verifier->verify($this->user, '000000'));
        }
    }

    public function test_an_empty_attempt_does_not_count_towards_the_lockout(): void
    {
        $this->twoFactorAuthenticationService->shouldNotReceive('verifyCode');
        $this->recoveryCodeService->shouldNotReceive('consume');

        for ($i = 0; $i <= TwoFactorVerifier::MAX_FAILED_ATTEMPTS; $i++) {
            $this->assertFalse($this->verifier->verify($this->user, null, null));
        }
    }
}
