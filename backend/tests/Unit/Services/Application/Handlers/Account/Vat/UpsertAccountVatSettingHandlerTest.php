<?php

namespace HiEvents\Tests\Unit\Services\Application\Handlers\Account\Vat;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\Status\VatValidationStatus;
use HiEvents\Jobs\Vat\ValidateVatNumberJob;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\UpsertAccountVatSettingDTO;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\ViesValidationResponseDTO;
use HiEvents\Services\Application\Handlers\Account\Vat\UpsertAccountVatSettingHandler;
use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class UpsertAccountVatSettingHandlerTest extends TestCase
{
    private AccountVatSettingRepositoryInterface $repository;
    private ViesValidationService $viesService;
    private LoggerInterface $logger;
    private UpsertAccountVatSettingHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->repository = Mockery::mock(AccountVatSettingRepositoryInterface::class);
        $this->viesService = Mockery::mock(ViesValidationService::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('info')->byDefault();
        $this->handler = new UpsertAccountVatSettingHandler(
            $this->repository,
            $this->viesService,
            $this->logger
        );
    }

    public function testHandleCreatesVatSettingWithSyncValidationSuccess(): void
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
            vatNumber: '1234567A',
            isTransientError: false,
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);
        $vatSetting->shouldReceive('getId')->andReturn(1);

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
                    && $data['vat_validation_status'] === VatValidationStatus::VALID->value
                    && $data['business_name'] === 'Test Company Ltd'
                    && $data['vat_country_code'] === 'IE';
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
        Queue::assertNotPushed(ValidateVatNumberJob::class);
    }

    public function testHandleQueuesJobOnTransientError(): void
    {
        $accountId = 123;
        $vatNumber = 'IE1234567A';
        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: $vatNumber,
        );

        $validationResponse = new ViesValidationResponseDTO(
            valid: false,
            countryCode: 'IE',
            vatNumber: '1234567A',
            isTransientError: true,
            errorMessage: 'VIES service is temporarily busy',
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);
        $vatSetting->shouldReceive('getId')->andReturn(1);

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
                    && $data['vat_validation_status'] === VatValidationStatus::PENDING->value
                    && $data['vat_country_code'] === 'IE';
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
        Queue::assertPushed(ValidateVatNumberJob::class);
    }

    public function testHandleDoesNotQueueJobOnInvalidVatNumber(): void
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
            vatNumber: '9999999ZZ',
            isTransientError: false,
            errorMessage: 'VAT number not found',
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);
        $vatSetting->shouldReceive('getId')->andReturn(1);

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
                    && $data['vat_validation_status'] === VatValidationStatus::INVALID->value;
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
        Queue::assertNotPushed(ValidateVatNumberJob::class);
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
        $vatSetting->shouldReceive('getId')->andReturn(1);

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
                    && $data['vat_validation_status'] === VatValidationStatus::INVALID->value
                    && $data['business_name'] === null;
            })
            ->andReturn($vatSetting);

        $result = $this->handler->handle($dto);

        $this->assertSame($vatSetting, $result);
        Queue::assertNotPushed(ValidateVatNumberJob::class);
    }

    public function testHandleCreatesVatSettingForNonRegistered(): void
    {
        $accountId = 123;
        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: false,
        );

        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);
        $vatSetting->shouldReceive('getId')->andReturn(1);

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
        Queue::assertNotPushed(ValidateVatNumberJob::class);
    }

    public function testHandleDoesNotValidateIfVatNumberUnchanged(): void
    {
        $accountId = 123;
        $existingId = 456;
        $vatNumber = 'DE123456789';

        $existing = Mockery::mock(AccountVatSettingDomainObject::class);
        $existing->shouldReceive('getId')->andReturn($existingId);
        $existing->shouldReceive('getVatNumber')->andReturn($vatNumber);

        $dto = new UpsertAccountVatSettingDTO(
            accountId: $accountId,
            vatRegistered: true,
            vatNumber: $vatNumber,
        );

        $updated = Mockery::mock(AccountVatSettingDomainObject::class);
        $updated->shouldReceive('getId')->andReturn($existingId);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn($existing);

        $this->viesService
            ->shouldNotReceive('validateVatNumber');

        $this->repository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with($existingId, Mockery::on(function ($data) use ($accountId, $vatNumber) {
                return $data['account_id'] === $accountId
                    && $data['vat_registered'] === true
                    && $data['vat_number'] === $vatNumber
                    && !isset($data['vat_validated']);
            }))
            ->andReturn($updated);

        $result = $this->handler->handle($dto);

        $this->assertSame($updated, $result);
        Queue::assertNotPushed(ValidateVatNumberJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
