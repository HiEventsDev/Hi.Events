<?php

namespace Tests\Unit\Services\Application\Handlers\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\TwoFactorRequiredByAccountException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\UpdateAccountTwoFactorRequirementHandler;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use Mockery as m;
use Tests\TestCase;

class UpdateAccountTwoFactorRequirementHandlerTest extends TestCase
{
    private AccountRepositoryInterface $accountRepository;

    private TwoFactorAuthenticationService $twoFactorAuthenticationService;

    private UpdateAccountTwoFactorRequirementHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $userRepository = m::mock(UserRepositoryInterface::class);
        $userRepository->shouldReceive('findById')->andReturn(new UserDomainObject);
        $this->twoFactorAuthenticationService = m::mock(TwoFactorAuthenticationService::class);

        $this->handler = new UpdateAccountTwoFactorRequirementHandler(
            $this->accountRepository,
            $userRepository,
            $this->twoFactorAuthenticationService,
        );
    }

    public function test_requiring_two_factor_needs_the_admin_to_be_enrolled(): void
    {
        $this->twoFactorAuthenticationService->shouldReceive('isEnabled')->andReturn(false);
        $this->accountRepository->shouldNotReceive('updateFromArray');

        $this->expectException(TwoFactorRequiredByAccountException::class);

        $this->handler->handle(accountId: 1, actingUserId: 2, required: true);
    }

    public function test_enrolled_admin_can_require_two_factor(): void
    {
        $this->twoFactorAuthenticationService->shouldReceive('isEnabled')->andReturn(true);
        $account = new AccountDomainObject;
        $this->accountRepository->shouldReceive('updateFromArray')->once()
            ->with(1, ['require_two_factor_authentication' => true])
            ->andReturn($account);

        $this->assertSame($account, $this->handler->handle(accountId: 1, actingUserId: 2, required: true));
    }

    public function test_turning_the_requirement_off_needs_no_enrolment(): void
    {
        $this->twoFactorAuthenticationService->shouldNotReceive('isEnabled');
        $this->accountRepository->shouldReceive('updateFromArray')->once()
            ->with(1, ['require_two_factor_authentication' => false])
            ->andReturn(new AccountDomainObject);

        $this->handler->handle(accountId: 1, actingUserId: 2, required: false);
    }
}
