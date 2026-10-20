<?php

namespace Tests\Unit\Services\Infrastructure\Jobs;

use HiEvents\Services\Infrastructure\Jobs\Enum\JobStatusEnum;
use HiEvents\Services\Infrastructure\Jobs\JobPollingService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class JobPollingServiceTest extends TestCase
{
    public function test_check_job_status_returns_not_found_for_unknown_batch(): void
    {
        Bus::shouldReceive('findBatch')->with('missing-uuid')->andReturn(null);

        $result = (new JobPollingService)->checkJobStatus('missing-uuid');

        $this->assertSame(JobStatusEnum::NOT_FOUND, $result->status);
    }

    public function test_check_job_status_returns_failed_when_batch_cancelled(): void
    {
        $batch = Mockery::mock(Batch::class);
        $batch->shouldReceive('cancelled')->andReturn(true);

        Bus::shouldReceive('findBatch')->with('uuid-1')->andReturn($batch);

        $result = (new JobPollingService)->checkJobStatus('uuid-1');

        $this->assertSame(JobStatusEnum::FAILED, $result->status);
    }

    public function test_check_job_status_returns_failed_when_batch_has_failed_jobs(): void
    {
        $batch = Mockery::mock(Batch::class);
        $batch->shouldReceive('cancelled')->andReturn(false);
        $batch->failedJobs = 1;

        Bus::shouldReceive('findBatch')->with('uuid-2')->andReturn($batch);

        $result = (new JobPollingService)->checkJobStatus('uuid-2');

        $this->assertSame(JobStatusEnum::FAILED, $result->status);
    }

    public function test_check_job_status_returns_finished_when_batch_finished(): void
    {
        $batch = Mockery::mock(Batch::class);
        $batch->shouldReceive('cancelled')->andReturn(false);
        $batch->failedJobs = 0;
        $batch->shouldReceive('finished')->andReturn(true);

        Bus::shouldReceive('findBatch')->with('uuid-3')->andReturn($batch);

        $result = (new JobPollingService)->checkJobStatus('uuid-3');

        $this->assertSame(JobStatusEnum::FINISHED, $result->status);
    }

    public function test_check_job_status_returns_in_progress_when_batch_running(): void
    {
        $batch = Mockery::mock(Batch::class);
        $batch->shouldReceive('cancelled')->andReturn(false);
        $batch->failedJobs = 0;
        $batch->shouldReceive('finished')->andReturn(false);

        Bus::shouldReceive('findBatch')->with('uuid-4')->andReturn($batch);

        $result = (new JobPollingService)->checkJobStatus('uuid-4');

        $this->assertSame(JobStatusEnum::IN_PROGRESS, $result->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
