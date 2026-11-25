<?php

namespace Tests\Unit\Jobs\TicketResale;

use HiEvents\Jobs\TicketResale\ProcessResaleRefundJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Tests\TestCase;

class ProcessResaleRefundJobTest extends TestCase
{
    public function test_only_one_refund_job_runs_per_listing_for_as_long_as_its_retries_can_last(): void
    {
        $job = new ProcessResaleRefundJob(42);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('42', $job->uniqueId());
        $this->assertGreaterThanOrEqual(array_sum($job->backoff()) + 3 * 3600, $job->uniqueFor);
    }
}
