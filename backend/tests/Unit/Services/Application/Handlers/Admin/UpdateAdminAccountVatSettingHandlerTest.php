<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DataTransferObjects\UpdateAdminAccountVatSettingDTO;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\UpdateAdminAccountVatSettingHandler;
use Mockery;
use Tests\TestCase;

class UpdateAdminAccountVatSettingHandlerTest extends TestCase
{
    private AccountVatSettingRepositoryInterface $repository;
    private UpdateAdminAccountVatSettingHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(AccountVatSettingRepositoryInterface::class);
        $this->handler = new UpdateAdminAccountVatSettingHandler($this->repository);
    }

    public function testHandleCreatesNewVatSetting(): void
    {
        $accountId = 123;
        $dto = new UpdateAdminAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: 'DE123456789',
            vatValidated: true,
            businessName: 'Test Company',
            businessAddress: '123 Test St',
            vatCountryCode: 'DE',
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($accountId) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === 'DE123456789'
                    && $data['vat_validated'] === true
                    && $data['business_name'] === 'Test Company'
                    && $data['business_address'] === '123 Test St'
                    && $data['vat_country_code'] === 'DE';
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
    }

    public function testHandleUpdatesExistingVatSetting(): void
    {
        $accountId = 123;
        $existingId = 456;

        $existing = Mockery::mock(AccountVatSettingDomainObject::class);
        $existing->shouldReceive('getId')->andReturn($existingId);

        $dto = new UpdateAdminAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: 'IE1234567A',
            vatValidated: false,
            businessName: 'Updated Company',
            businessAddress: '456 New St',
            vatCountryCode: 'IE',
        );

        $updated = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn($existing);

        $this->repository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($existingId, Mockery::on(function ($data) use ($accountId) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === 'IE1234567A'
                    && $data['vat_validated'] === false
                    && $data['business_name'] === 'Updated Company'
                    && $data['business_address'] === '456 New St'
                    && $data['vat_country_code'] === 'IE';
            }))
            ->andReturn($updated);

        $result = $this->handler->handle($dto);

        $this->assertSame($updated, $result);
    }

    public function testHandleCreatesNonRegisteredVatSetting(): void
    {
        $accountId = 123;
        $dto = new UpdateAdminAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: false,
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($accountId) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === false
                    && $data['vat_number'] === null
                    && $data['vat_validated'] === false
                    && $data['business_name'] === null
                    && $data['business_address'] === null
                    && $data['vat_country_code'] === null;
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
    }

    public function testHandleWithPartialData(): void
    {
        $accountId = 123;
        $dto = new UpdateAdminAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: 'FR12345678901',
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($accountId) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === 'FR12345678901'
                    && $data['vat_validated'] === false
                    && $data['business_name'] === null
                    && $data['business_address'] === null
                    && $data['vat_country_code'] === null;
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
