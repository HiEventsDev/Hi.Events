<?php

namespace HiEvents\Tests\Unit\Services\Application\Handlers\Account\Vat;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\UpsertAccountVatSettingDTO;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\ViesValidationResponseDTO;
use HiEvents\Services\Application\Handlers\Account\Vat\UpsertAccountVatSettingHandler;
use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Mockery;
use Tests\TestCase;

class UpsertAccountVatSettingHandlerTest extends TestCase
{
    private AccountVatSettingRepositoryInterface $repository;
    private ViesValidationService $viesService;
    private UpsertAccountVatSettingHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(AccountVatSettingRepositoryInterface::class);
        $this->viesService = Mockery::mock(ViesValidationService::class);
        $this->handler = new UpsertAccountVatSettingHandler($this->repository, $this->viesService);
    }

    public function testHandleCreatesVatSettingWithValidatedNumber(): void
    {
        $accountId = 123;
        $vatNumber = 'IE1234567A';
        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: $vatNumber,
        );

        $validationResponse = new ViesValidationResponseDTO(
            valid: true,
            businessName: 'Test Company Ltd',
            businessAddress: '123 Test Street',
            countryCode: 'IE',
            vatNumber: '1234567A'
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $this->viesService
            ->shouldReceive('validateVatNumber')
            ->with($vatNumber)
            ->once()
            ->andReturn($validationResponse);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($accountId, $vatNumber) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === $vatNumber
                    && $data['vat_validated'] === true
                    && $data['business_name'] === 'Test Company Ltd'
                    && isset($data['vat_validation_date']);
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
    }

    public function testHandleCreatesVatSettingWithInvalidNumber(): void
    {
        $accountId = 123;
        $vatNumber = 'IE9999999ZZ';
        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: $vatNumber,
        );

        $validationResponse = new ViesValidationResponseDTO(
            valid: false,
            countryCode: 'IE',
            vatNumber: '9999999ZZ'
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $this->viesService
            ->shouldReceive('validateVatNumber')
            ->with($vatNumber)
            ->once()
            ->andReturn($validationResponse);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($accountId, $vatNumber) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === $vatNumber
                    && $data['vat_validated'] === false
                    && $data['business_name'] === null;
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
    }

    public function testHandleCreatesVatSettingWithInvalidFormat(): void
    {
        $accountId = 123;
        $vatNumber = 'INVALID';
        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: $vatNumber,
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $this->viesService
            ->shouldNotReceive('validateVatNumber');

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($accountId) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === 'INVALID'
                    && $data['vat_validated'] === false
                    && $data['business_name'] === null;
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
    }

    public function testHandleCreatesVatSettingForNonRegistered(): void
    {
        $accountId = 123;
        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: false,
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $this->viesService
            ->shouldNotReceive('validateVatNumber');

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($accountId) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === false
                    && $data['vat_number'] === null
                    && $data['vat_validated'] === false;
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
    }

    public function testHandleUpdatesExistingVatSetting(): void
    {
        $accountId = 123;
        $existingId = 456;
        $vatNumber = 'DE123456789';

        $existing = Mockery::mock(AccountVatSettingDomainObject::class);
        $existing->shouldReceive('getId')->andReturn($existingId);

        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: $vatNumber,
        );

        $validationResponse = new ViesValidationResponseDTO(
            valid: true,
            businessName: 'Test GmbH',
            businessAddress: 'Berlin, Germany',
            countryCode: 'DE',
            vatNumber: '123456789'
        );

        $updated = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn($existing);

        $this->viesService
            ->shouldReceive('validateVatNumber')
            ->with($vatNumber)
            ->once()
            ->andReturn($validationResponse);

        $this->repository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($existingId, Mockery::on(function ($data) use ($accountId, $vatNumber) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === $vatNumber
                    && $data['vat_validated'] === true
                    && $data['business_name'] === 'Test GmbH';
            }))
            ->andReturn($updated);

        $result = $this->handler->handle($dto);

        $this->assertSame($updated, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
