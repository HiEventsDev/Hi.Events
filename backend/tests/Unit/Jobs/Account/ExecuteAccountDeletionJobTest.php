<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Account;

use HiEvents\Jobs\Account\ExecuteAccountDeletionJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Tests\TestCase;

class ExecuteAccountDeletionJobTest extends TestCase
{
    public function test_job_is_unique_per_deletion_request(): void
    {
        $job = new ExecuteAccountDeletionJob(42);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('42', (string) $job->uniqueId());
    }
}
