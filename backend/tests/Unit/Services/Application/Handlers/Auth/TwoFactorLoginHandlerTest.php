<?php

namespace Tests\Unit\Services\Application\Handlers\Auth;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\TwoFactorChallengeExpiredException;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use HiEvents\Mail\User\TwoFactorRecoveryCodeUsedMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Auth\DTO\TwoFactorLoginDTO;
use HiEvents\Services\Application\Handlers\Auth\TwoFactorLoginHandler;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\TwoFactor\DTO\TwoFactorChallengeDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\RecoveryCodeService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorChallengeService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorVerifier;
use Illuminate\Contracts\Mail\Mailer;
use Mockery as m;
use Tests\TestCase;

class TwoFactorLoginHandlerTest extends TestCase
{
    private TwoFactorChallengeService $challengeService;

    private TwoFactorAuthenticationService $twoFactorAuthenticationService;

    private TwoFactorVerifier $twoFactorVerifier;

    private RecoveryCodeService $recoveryCodeService;

    private TrustedDeviceService $trustedDeviceService;

    private LoginService $loginService;

    private UserRepositoryInterface $userRepository;

    private AccountUserRepositoryInterface $accountUserRepository;

    private Mailer $mailer;

    private TwoFactorLoginHandler $handler;

    private UserDomainObject $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->challengeService = m::mock(TwoFactorChallengeService::class);
        $this->twoFactorAuthenticationService = m::mock(TwoFactorAuthenticationService::class);
        $this->twoFactorVerifier = m::mock(TwoFactorVerifier::class);
        $this->recoveryCodeService = m::mock(RecoveryCodeService::class);
        $this->trustedDeviceService = m::mock(TrustedDeviceService::class);
        $this->loginService = m::mock(LoginService::class);
        $this->userRepository = m::mock(UserRepositoryInterface::class);
        $this->accountUserRepository = m::mock(AccountUserRepositoryInterface::class);
        $this->mailer = m::mock(Mailer::class);

        $this->handler = new TwoFactorLoginHandler(
            $this->challengeService,
            $this->twoFactorAuthenticationService,
            $this->twoFactorVerifier,
            $this->recoveryCodeService,
            $this->trustedDeviceService,
            $this->loginService,
            $this->userRepository,
            $this->accountUserRepository,
            $this->mailer,
        );

        $this->user = (new UserDomainObject)->setId(9)->setEmail('organizer@example.com')->setLocale('en');
        $this->userRepository->shouldReceive('findFirstWhere')->with(['id' => 9])->andReturn($this->user);
        $this->twoFactorAuthenticationService->shouldReceive('isEnabled')->andReturn(true);
    }

    public function test_missing_challenge_is_rejected(): void
    {
        $this->challengeService->shouldReceive('find')->with('token')->andReturn(null);

        $this->expectException(TwoFactorChallengeExpiredException::class);

        $this->handler->handle($this->dto(code: '123456'));
    }

    public function test_valid_code_issues_a_token_and_trusts_the_device_when_asked(): void
    {
        $challenge = new TwoFactorChallengeDTO(userId: 9, verified: false);
        $this->challengeService->shouldReceive('find')->andReturn($challenge);
        $this->twoFactorVerifier->shouldReceive('verify')->with($this->user, '123456', null)->andReturn(true);
        $this->challengeService->shouldReceive('markVerified')->once()->with('token', $challenge, null)
            ->andReturn(new TwoFactorChallengeDTO(userId: 9, verified: true));
        $this->trustedDeviceService->shouldReceive('trust')->once()->with(9, 'Firefox', '10.0.0.1')->andReturn('device-token');
        $this->loginService->shouldReceive('completeLogin')->once()->with($this->user, null)
            ->andReturn(new LoginResponse(accounts: collect(), token: 'jwt', user: $this->user, accountId: 3));
        $this->challengeService->shouldReceive('forget')->once()->with('token');
        $this->accountUserRepository->shouldReceive('updateWhere')->once();

        $response = $this->handler->handle($this->dto(code: '123456', rememberDevice: true));

        $this->assertSame('jwt', $response->token);
        $this->assertSame('device-token', $response->trustedDeviceToken);
    }

    public function test_wrong_code_records_a_failed_attempt(): void
    {
        $challenge = new TwoFactorChallengeDTO(userId: 9, verified: false);
        $this->challengeService->shouldReceive('find')->andReturn($challenge);
        $this->twoFactorVerifier->shouldReceive('verify')->andReturn(false);
        $this->challengeService->shouldNotReceive('markVerified');
        $this->loginService->shouldNotReceive('completeLogin');

        $this->expectException(InvalidTwoFactorCodeException::class);

        $this->handler->handle($this->dto(code: '000000'));
    }

    public function test_a_locked_out_user_loses_the_challenge(): void
    {
        $this->challengeService->shouldReceive('find')->andReturn(new TwoFactorChallengeDTO(userId: 9, verified: false));
        $this->twoFactorVerifier->shouldReceive('verify')->andThrow(new TwoFactorLockedOutException('Too many'));
        $this->challengeService->shouldReceive('forget')->once()->with('token');

        $this->expectException(TwoFactorChallengeExpiredException::class);

        $this->handler->handle($this->dto(code: '000000'));
    }

    public function test_recovery_code_reports_remaining_codes_and_emails_the_user(): void
    {
        $challenge = new TwoFactorChallengeDTO(userId: 9, verified: false);
        $this->challengeService->shouldReceive('find')->andReturn($challenge);
        $this->twoFactorVerifier->shouldReceive('verify')->once()->with($this->user, null, 'abcde-fghjk')->andReturn(true);
        $this->userRepository->shouldReceive('findById')->with(9)->andReturn($this->user);
        $this->recoveryCodeService->shouldReceive('remaining')->andReturn(4);
        $this->mailer->shouldReceive('to->locale->send')->once()->with(m::type(TwoFactorRecoveryCodeUsedMail::class));
        $this->challengeService->shouldReceive('markVerified')->once()->with('token', $challenge, 4)
            ->andReturn(new TwoFactorChallengeDTO(userId: 9, verified: true, recoveryCodesRemaining: 4));
        $this->loginService->shouldReceive('completeLogin')
            ->andReturn(new LoginResponse(accounts: collect(), token: 'jwt', user: $this->user, accountId: 3));
        $this->challengeService->shouldReceive('forget');
        $this->accountUserRepository->shouldReceive('updateWhere');

        $response = $this->handler->handle($this->dto(recoveryCode: 'abcde-fghjk'));

        $this->assertSame(4, $response->recoveryCodesRemaining);
        $this->assertNull($response->trustedDeviceToken);
    }

    public function test_verified_challenge_selects_an_account_without_another_code(): void
    {
        $this->challengeService->shouldReceive('find')->andReturn(new TwoFactorChallengeDTO(userId: 9, verified: true));
        $this->twoFactorVerifier->shouldNotReceive('verify');
        $this->trustedDeviceService->shouldNotReceive('trust');
        $this->loginService->shouldReceive('completeLogin')->once()->with($this->user, 5)
            ->andReturn(new LoginResponse(accounts: collect(), token: 'jwt', user: $this->user, accountId: 5));
        $this->challengeService->shouldReceive('forget')->once();
        $this->accountUserRepository->shouldReceive('updateWhere')->once();

        $response = $this->handler->handle($this->dto(accountId: 5, rememberDevice: true));

        $this->assertSame('jwt', $response->token);
    }

    public function test_challenge_survives_until_an_account_is_chosen(): void
    {
        $this->challengeService->shouldReceive('find')->andReturn(new TwoFactorChallengeDTO(userId: 9, verified: false));
        $this->twoFactorVerifier->shouldReceive('verify')->andReturn(true);
        $this->challengeService->shouldReceive('markVerified')
            ->andReturn(new TwoFactorChallengeDTO(userId: 9, verified: true));
        $this->loginService->shouldReceive('completeLogin')
            ->andReturn(new LoginResponse(accounts: collect([1, 2]), token: null, user: $this->user));
        $this->challengeService->shouldNotReceive('forget');
        $this->accountUserRepository->shouldNotReceive('updateWhere');

        $response = $this->handler->handle($this->dto(code: '123456'));

        $this->assertNull($response->token);
        $this->assertCount(2, $response->accounts);
    }

    private function dto(
        ?string $code = null,
        ?string $recoveryCode = null,
        ?int $accountId = null,
        bool $rememberDevice = false,
    ): TwoFactorLoginDTO {
        return new TwoFactorLoginDTO(
            challengeToken: 'token',
            code: $code,
            recoveryCode: $recoveryCode,
            accountId: $accountId,
            rememberDevice: $rememberDevice,
            userAgent: 'Firefox',
            ipAddress: '10.0.0.1',
        );
    }
}
