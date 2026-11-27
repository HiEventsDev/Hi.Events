<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmBulkOperationRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmImportRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneCrmBulkOperationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SNAPSHOT_ROWS_PER_RUN = 20_000;

    private const KEEP_SNAPSHOTS_FOR_DAYS = 7;

    public int $timeout = 40;

    public function handle(CrmBulkOperationRepositoryInterface $bulkOperationRepository, CrmImportRepositoryInterface $importRepository): void
    {
        $importRepository->failStalled();
        $bulkOperationRepository->failStalled();

        $pruned = $bulkOperationRepository->pruneSnapshotsFinishedBefore(
            now()->subDays(self::KEEP_SNAPSHOTS_FOR_DAYS),
            self::SNAPSHOT_ROWS_PER_RUN,
        );

        if ($pruned >= self::SNAPSHOT_ROWS_PER_RUN) {
            self::dispatch();
        }
    }
}
