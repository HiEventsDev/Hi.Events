<?php

namespace HiEvents\Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DataTransferObjects\UpdateAccountConfigurationDTO;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\UpdateAccountConfigurationHandler;
use Mockery;
use Tests\TestCase;

class UpdateAccountConfigurationHandlerTest extends TestCase
{
    private AccountConfigurationRepositoryInterface $configurationRepository;
    private AccountRepositoryInterface $accountRepository;
    private UpdateAccountConfigurationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurationRepository = Mockery::mock(AccountConfigurationRepositoryInterface::class);
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->handler = new UpdateAccountConfigurationHandler(
            $this->configurationRepository,
            $this->accountRepository
        );
    }

    public function testHandleUpdatesExistingConfiguration(): void
    {
        $accountId = 123;
        $configurationId = 456;
        $applicationFees = ['fixed' => 100, 'percentage' => 2.5];

        $existingConfig = Mockery::mock(AccountConfigurationDomainObject::class);
        $existingConfig->shouldReceive('getId')->andReturn($configurationId);

        $account = Mockery::mock(AccountDomainObject::class);
        $account->shouldReceive('getConfiguration')->andReturn($existingConfig);

        $updatedConfig = Mockery::mock(AccountConfigurationDomainObject::class);

        $dto = new UpdateAccountConfigurationDTO(
            accountId: $accountId,
            applicationFees: $applicationFees,
        );

        $this->accountRepository
            ->shouldReceive('loadRelation')
            ->with('configuration')
            ->once()
            ->andReturnSelf();

        $this->accountRepository
            ->shouldReceive('findById')
            ->with($accountId)
            ->once()
            ->andReturn($account);

        $this->configurationRepository
            ->shouldReceive('updateFromArray')
            ->with($configurationId, ['application_fees' => $applicationFees])
            ->once()
            ->andReturn($updatedConfig);

        $result = $this->handler->handle($dto);

        $this->assertSame($updatedConfig, $result);
    }

    public function testHandleCreatesNewConfigurationWhenNoneExists(): void
    {
        $accountId = 123;
        $applicationFees = ['fixed' => 50, 'percentage' => 1.5];

        $account = Mockery::mock(AccountDomainObject::class);
        $account->shouldReceive('getConfiguration')->andReturn(null);
        $account->shouldReceive('getId')->andReturn($accountId);

        $newConfig = Mockery::mock(AccountConfigurationDomainObject::class);
        $newConfig->shouldReceive('getId')->andReturn(789);

        $dto = new UpdateAccountConfigurationDTO(
            accountId: $accountId,
            applicationFees: $applicationFees,
        );

        $this->accountRepository
            ->shouldReceive('loadRelation')
            ->with('configuration')
            ->once()
            ->andReturnSelf();

        $this->accountRepository
            ->shouldReceive('findById')
            ->with($accountId)
            ->once()
            ->andReturn($account);

        $this->configurationRepository
            ->shouldReceive('create')
            ->with([
                'name' => 'Account Configuration',
                'is_system_default' => false,
                'application_fees' => $applicationFees,
            ])
            ->once()
            ->andReturn($newConfig);

        $this->accountRepository
            ->shouldReceive('updateFromArray')
            ->with($accountId, ['account_configuration_id' => 789])
            ->once()
            ->andReturn($account);

        $result = $this->handler->handle($dto);

        $this->assertSame($newConfig, $result);
    }

    public function testHandleWithZeroFees(): void
    {
        $accountId = 123;
        $configurationId = 456;
        $applicationFees = ['fixed' => 0, 'percentage' => 0];

        $existingConfig = Mockery::mock(AccountConfigurationDomainObject::class);
        $existingConfig->shouldReceive('getId')->andReturn($configurationId);

        $account = Mockery::mock(AccountDomainObject::class);
        $account->shouldReceive('getConfiguration')->andReturn($existingConfig);

        $updatedConfig = Mockery::mock(AccountConfigurationDomainObject::class);

        $dto = new UpdateAccountConfigurationDTO(
            accountId: $accountId,
            applicationFees: $applicationFees,
        );

        $this->accountRepository
            ->shouldReceive('loadRelation')
            ->with('configuration')
            ->once()
            ->andReturnSelf();

        $this->accountRepository
            ->shouldReceive('findById')
            ->with($accountId)
            ->once()
            ->andReturn($account);

        $this->configurationRepository
            ->shouldReceive('updateFromArray')
            ->with($configurationId, ['application_fees' => $applicationFees])
            ->once()
            ->andReturn($updatedConfig);

        $result = $this->handler->handle($dto);

        $this->assertSame($updatedConfig, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
