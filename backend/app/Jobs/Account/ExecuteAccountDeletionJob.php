<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Account;

use HiEvents\Services\Domain\Account\AccountDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteAccountDeletionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $deletionRequestId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->deletionRequestId;
    }

    /**
     * @throws Throwable
     */
    public function handle(AccountDeletionService $accountDeletionService): void
    {
        $accountDeletionService->executeDeletion($this->deletionRequestId);
    }
}
