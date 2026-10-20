<?php

namespace HiEvents\Services\Infrastructure\Jobs;

use HiEvents\Services\Infrastructure\Jobs\DTO\JobPollingResultDTO;
use HiEvents\Services\Infrastructure\Jobs\Enum\JobStatusEnum;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class JobPollingService
{
    private const STORAGE_DISK = 's3-private';

    public function startJob(string $jobName, array $jobs): JobPollingResultDTO
    {
        $batch = Bus::batch($jobs)
            ->name($jobName)
            ->dispatch();

        return new JobPollingResultDTO(
            status: JobStatusEnum::IN_PROGRESS,
            message: 'Job started successfully',
            jobUuid: $batch->id,
        );
    }

    public function checkJobStatus(string $jobUuid, ?string $filePath = null, ?string $expectedName = null): JobPollingResultDTO
    {
        $batch = Bus::findBatch($jobUuid);

        if ($batch && $expectedName !== null && $batch->name !== $expectedName) {
            $batch = null;
        }

        if (! $batch) {
            return new JobPollingResultDTO(
                status: JobStatusEnum::NOT_FOUND,
                message: __('Job not found'),
                jobUuid: $jobUuid,
            );
        }

        if ($batch->cancelled() || $batch->failedJobs > 0) {
            return new JobPollingResultDTO(
                status: JobStatusEnum::FAILED,
                message: __('Job failed'),
                jobUuid: $jobUuid,
            );
        }

        if ($batch->finished()) {
            if ($filePath && ! Storage::disk(self::STORAGE_DISK)->exists($filePath)) {
                return new JobPollingResultDTO(
                    status: JobStatusEnum::NOT_FOUND,
                    message: __('Export file not found'),
                    jobUuid: $jobUuid,
                );
            }

            return new JobPollingResultDTO(
                status: JobStatusEnum::FINISHED,
                message: __('Job completed successfully'),
                jobUuid: $jobUuid,
                downloadUrl: $filePath
                    ? Storage::disk(self::STORAGE_DISK)->temporaryUrl($filePath, now()->addMinutes(10))
                    : null,
            );
        }

        return new JobPollingResultDTO(
            status: JobStatusEnum::IN_PROGRESS,
            message: __('Job is still in progress'),
            jobUuid: $jobUuid,
        );
    }
}
