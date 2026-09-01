<?php

namespace Tests\Unit\Jobs\Vat;

use HiEvents\Jobs\Vat\ValidateVatNumberJob;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;
use HiEvents\Services\Infrastructure\Vat\ViesValidationService;
use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Tests\TestCase;

class ValidateVatNumberJobTest extends TestCase
{
    public function test_a_payload_queued_by_a_previous_release_is_skipped(): void
    {
        $job = (new ReflectionClass(ValidateVatNumberJob::class))->newInstanceWithoutConstructor();

        $viesService = Mockery::mock(ViesValidationService::class);
        $viesService->shouldNotReceive('validateVatNumber');

        $repository = Mockery::mock(OrganizerVatSettingRepositoryInterface::class);
        $repository->shouldNotReceive('updateFromArray');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once();

        $job->handle($viesService, $repository, $logger);
    }
}
