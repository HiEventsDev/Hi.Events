<?php

namespace Tests\Unit\Services\Application\Handlers\User\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Exceptions\TwoFactorRequiredByAccountException;
use HiEvents\Mail\User\TwoFactorDisabledMail;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\TwoFactor\DisableTwoFactorHandler;
use HiEvents\Services\Application\Handlers\User\TwoFactor\DTO\DisableTwoFactorDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorRequirementService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorVerifier;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Mockery as m;
use Tests\TestCase;

class DisableTwoFactorHandlerTest extends TestCase
{
    private TwoFactorAuthenticationService $twoFactorAuthenticationService;

    private TwoFactorVerifier $twoFactorVerifier;

    private TrustedDeviceService $trustedDeviceService;

    private TwoFactorRequirementService $requirementService;

    private Hasher $hasher;

    private Mailer $mailer;

    private DisableTwoFactorHandler $handler;

    private UserDomainObject $user;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepository = m::mock(UserRepositoryInterface::class);
        $this->twoFactorAuthenticationService = m::mock(TwoFactorAuthenticationService::class);
        $this->twoFactorVerifier = m::mock(TwoFactorVerifier::class);
        $this->trustedDeviceService = m::mock(TrustedDeviceService::class);
        $this->requirementService = m::mock(TwoFactorRequirementService::class);
        $this->hasher = m::mock(Hasher::class);
        $this->mailer = m::mock(Mailer::class);
        $databaseManager = m::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new DisableTwoFactorHandler(
            $userRepository,
            $this->twoFactorAuthenticationService,
            $this->twoFactorVerifier,
            $this->trustedDeviceService,
            $this->requirementService,
            $this->hasher,
            $databaseManager,
            $this->mailer,
        );

        $this->user = (new UserDomainObject)->setId(2)->setPassword('hashed')->setEmail('a@example.com')->setLocale('en');
        $userRepository->shouldReceive('findById')->with(2)->andReturn($this->user);
        $this->twoFactorAuthenticationService->shouldReceive('isEnabled')->andReturn(true);
    }

    public function test_cannot_disable_while_an_account_requires_it(): void
    {
        $this->requirementService->shouldReceive('namesOfAccountsRequiringTwoFactor')->andReturn(collect(['Acme']));
        $this->twoFactorAuthenticationService->shouldNotReceive('disable');

        $this->expectException(TwoFactorRequiredByAccountException::class);

        $this->handler->handle($this->dto());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->requirementService->shouldReceive('namesOfAccountsRequiringTwoFactor')->andReturn(collect());
        $this->hasher->shouldReceive('check')->with('secret', 'hashed')->andReturn(false);

        $this->expectException(PasswordInvalidException::class);

        $this->handler->handle($this->dto());
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->requirementService->shouldReceive('namesOfAccountsRequiringTwoFactor')->andReturn(collect());
        $this->hasher->shouldReceive('check')->andReturn(true);
        $this->twoFactorVerifier->shouldReceive('verify')->with($this->user, '123456', null)->andReturn(false);

        $this->expectException(InvalidTwoFactorCodeException::class);

        $this->handler->handle($this->dto());
    }

    public function test_disabling_revokes_trusted_devices_and_notifies_the_user(): void
    {
        $this->requirementService->shouldReceive('namesOfAccountsRequiringTwoFactor')->andReturn(collect());
        $this->hasher->shouldReceive('check')->andReturn(true);
        $this->twoFactorVerifier->shouldReceive('verify')->with($this->user, null, 'abcde-fghjk')->andReturn(true);
        $this->twoFactorAuthenticationService->shouldReceive('disable')->once()->with(2);
        $this->trustedDeviceService->shouldReceive('revokeAll')->once()->with(2);
        $this->mailer->shouldReceive('to->locale->send')->once()->with(m::type(TwoFactorDisabledMail::class));

        $this->handler->handle(new DisableTwoFactorDTO(userId: 2, password: 'secret', code: null, recoveryCode: 'abcde-fghjk'));
    }

    private function dto(): DisableTwoFactorDTO
    {
        return new DisableTwoFactorDTO(userId: 2, password: 'secret', code: '123456', recoveryCode: null);
    }
}
