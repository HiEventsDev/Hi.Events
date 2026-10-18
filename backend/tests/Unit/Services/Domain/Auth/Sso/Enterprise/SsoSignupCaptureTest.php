<?php

namespace Tests\Unit\Services\Domain\Auth\Sso\Enterprise;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\Enterprise\Sso\Exceptions\SsoRequiredException;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoDomainService;
use HiEvents\Enterprise\Sso\Services\Domain\SsoBreakGlassService;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SsoSignupCaptureTest extends TestCase
{
    private AccountSsoDomainService&MockInterface $domainService;

    private AccountSsoConnectionRepositoryInterface&MockInterface $connectionRepository;

    private SsoEnforcementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domainService = Mockery::mock(AccountSsoDomainService::class);
        $this->connectionRepository = Mockery::mock(AccountSsoConnectionRepositoryInterface::class);

        $this->service = new SsoEnforcementService(
            $this->connectionRepository,
            Mockery::mock(AccountUserRepositoryInterface::class),
            Mockery::mock(UserRepositoryInterface::class),
            $this->domainService,
            Mockery::mock(TwoFactorAuthenticationService::class),
            Mockery::mock(UserSessionRevocationService::class),
            Mockery::mock(SsoBreakGlassService::class),
            Mockery::mock(UserIdentityRepositoryInterface::class),
        );
    }

    public function test_an_email_off_every_usable_domain_can_sign_up(): void
    {
        $this->domainService->shouldReceive('findUsableDomainForEmail')->with('grace@acme.test')->andReturnNull();
        $this->connectionRepository->shouldNotReceive('findFirstWhere');

        $this->service->assertSignupAllowed('grace@acme.test');

        $this->addToAssertionCount(1);
    }

    public function test_an_email_on_a_domain_whose_account_requires_sso_cannot_sign_up(): void
    {
        $this->givenConnection(AccountSsoConnectionStatus::ACTIVE, enforce: true);

        try {
            $this->service->assertSignupAllowed('grace@acme.test');
            $this->fail('Expected SsoRequiredException');
        } catch (SsoRequiredException $exception) {
            $this->assertSame('grace@acme.test', $exception->render()->getData(true)['context']['email']);
        }
    }

    public function test_an_email_on_a_domain_whose_connection_does_not_enforce_can_sign_up(): void
    {
        $this->givenConnection(AccountSsoConnectionStatus::ACTIVE, enforce: false);

        $this->service->assertSignupAllowed('grace@acme.test');

        $this->addToAssertionCount(1);
    }

    public function test_an_email_on_a_domain_whose_connection_is_off_can_sign_up(): void
    {
        $this->givenConnection(AccountSsoConnectionStatus::INACTIVE, enforce: true);

        $this->service->assertSignupAllowed('grace@acme.test');

        $this->addToAssertionCount(1);
    }

    private function givenConnection(AccountSsoConnectionStatus $status, bool $enforce): void
    {
        $this->domainService->shouldReceive('findUsableDomainForEmail')
            ->with('grace@acme.test')
            ->andReturn((new AccountSsoDomainDomainObject)->setAccountId(7)->setDomain('acme.test'));
        $this->connectionRepository->shouldReceive('findFirstWhere')
            ->with(['account_id' => 7])
            ->andReturn((new AccountSsoConnectionDomainObject)->setId(3)->setAccountId(7)->setStatus($status->value)->setEnforceSso($enforce));
    }
}
