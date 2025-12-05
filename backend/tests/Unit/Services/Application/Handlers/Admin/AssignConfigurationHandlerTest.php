<?php

namespace HiEvents\Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\AssignConfigurationHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Tests\TestCase;

class AssignConfigurationHandlerTest extends TestCase
{
    private AccountRepositoryInterface $accountRepository;
    private AccountConfigurationRepositoryInterface $configurationRepository;
    private AssignConfigurationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->configurationRepository = Mockery::mock(AccountConfigurationRepositoryInterface::class);
        $this->handler = new AssignConfigurationHandler(
            $this->accountRepository,
            $this->configurationRepository
        );
    }

    public function testHandleSuccessfullyAssignsConfiguration(): void
    {
        $accountId = 123;
        $configurationId = 456;
        $configuration = Mockery::mock(AccountConfigurationDomainObject::class);
        $account = Mockery::mock(AccountDomainObject::class);

        $this->configurationRepository
            ->shouldReceive('findById')
            ->with($configurationId)
            ->once()
            ->andReturn($configuration);

        $this->accountRepository
            ->shouldReceive('updateFromArray')
            ->with($accountId, ['account_configuration_id' => $configurationId])
            ->once()
            ->andReturn($account);

        $this->handler->handle($accountId, $configurationId);

        $this->assertTrue(true);
    }

    public function testHandleThrowsExceptionWhenConfigurationNotFound(): void
    {
        $accountId = 123;
        $configurationId = 999;

        $this->configurationRepository
            ->shouldReceive('findById')
            ->with($configurationId)
            ->once()
            ->andThrow(new ModelNotFoundException());

        $this->accountRepository
            ->shouldNotReceive('updateFromArray');

        $this->expectException(ModelNotFoundException::class);

        $this->handler->handle($accountId, $configurationId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
