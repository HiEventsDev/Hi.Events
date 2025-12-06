<?php

namespace Tests\Unit\Jobs\Vat;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\Status\VatValidationStatus;
use HiEvents\Jobs\Vat\ValidateVatNumberJob;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Vat\DTO\ViesValidationResponseDTO;
use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ValidateVatNumberJobTest extends TestCase
{
    private ViesValidationService $viesService;
    private AccountVatSettingRepositoryInterface $repository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viesService = Mockery::mock(ViesValidationService::class);
        $this->repository = Mockery::mock(AccountVatSettingRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('info')->byDefault();
        $this->logger->shouldReceive('warning')->byDefault();
    }

    public function testJobUpdatesSettingsOnSuccessfulValidation(): void
    {
        $accountVatSettingId = 123;
        $vatNumber = 'IE1234567A';

        $job = new ValidateVatNumberJob($accountVatSettingId, $vatNumber);

        $validationResponse = new ViesValidationResponseDTO(
            valid: true,
            businessName: 'Test Company Ltd',
            businessAddress: '123 Test Street',
            countryCode: 'IE',
            vatNumber: '1234567A',
            isTransientError: false,
        );

        $domainObject = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->viesService
            ->shouldReceive('validateVatNumber')
            ->with($vatNumber)
            ->once()
            ->andReturn($validationResponse);

        $this->repository
            ->shouldReceive('updateFromArray')
            ->twice()
            ->withArgs(function ($id, $data) use ($accountVatSettingId) {
                if ($id !== $accountVatSettingId) {
                    return false;
                }

                if (isset($data['vat_validation_status']) && $data['vat_validation_status'] === VatValidationStatus::VALIDATING->value) {
                    return true;
                }

                if (isset($data['vat_validated']) && $data['vat_validated'] === true) {
                    return $data['vat_validation_status'] === VatValidationStatus::VALID->value
                        && $data['business_name'] === 'Test Company Ltd';
                }

                return false;
            })
            ->andReturn($domainObject);

        $job->handle($this->viesService, $this->repository, $this->logger);

        $this->assertTrue(true);
    }

    public function testJobUpdatesSettingsOnInvalidVatNumber(): void
    {
        $accountVatSettingId = 123;
        $vatNumber = 'IE9999999ZZ';

        $job = new ValidateVatNumberJob($accountVatSettingId, $vatNumber);

        $validationResponse = new ViesValidationResponseDTO(
            valid: false,
            countryCode: 'IE',
            vatNumber: '9999999ZZ',
            isTransientError: false,
            errorMessage: 'VAT number is not valid',
        );

        $domainObject = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->viesService
            ->shouldReceive('validateVatNumber')
            ->with($vatNumber)
            ->once()
            ->andReturn($validationResponse);

        $this->repository
            ->shouldReceive('updateFromArray')
            ->twice()
            ->withArgs(function ($id, $data) use ($accountVatSettingId) {
                if ($id !== $accountVatSettingId) {
                    return false;
                }

                if (isset($data['vat_validation_status']) && $data['vat_validation_status'] === VatValidationStatus::VALIDATING->value) {
                    return true;
                }

                if (isset($data['vat_validated']) && $data['vat_validated'] === false) {
                    return $data['vat_validation_status'] === VatValidationStatus::INVALID->value;
                }

                return false;
            })
            ->andReturn($domainObject);

        $job->handle($this->viesService, $this->repository, $this->logger);

        $this->assertTrue(true);
    }

    public function testJobHasCorrectRetryConfiguration(): void
    {
        $job = new ValidateVatNumberJob(1, 'IE1234567A');

        $this->assertEquals(15, $job->tries);
        $this->assertEquals(15, $job->maxExceptions);
        $this->assertEquals(15, $job->timeout);
    }

    public function testJobBackoffConfiguration(): void
    {
        $job = new ValidateVatNumberJob(1, 'IE1234567A');

        $backoffs = $job->backoff();

        $this->assertCount(15, $backoffs);
        $this->assertEquals(10, $backoffs[0]);
        $this->assertEquals(1800, $backoffs[14]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
