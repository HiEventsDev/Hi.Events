<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\DomainObjects\Generated\CrmBulkOperationDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmBulkOperationStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmBulkOperationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Bulk\BulkOperationProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessBulkOperationChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 40;

    public function __construct(
        public int $operationId,
        public int $afterContactId,
    ) {}

    public function handle(CrmBulkOperationRepositoryInterface $bulkOperationRepository, BulkOperationProcessor $processor): void
    {
        $operation = $bulkOperationRepository->findFirstWhere([
            CrmBulkOperationDomainObjectAbstract::ID => $this->operationId,
            CrmBulkOperationDomainObjectAbstract::STATUS => CrmBulkOperationStatus::RUNNING->value,
        ]);

        if ($operation === null) {
            return;
        }

        $lastContactId = $processor->processNextChunk($operation, $this->afterContactId);

        if ($lastContactId !== null) {
            self::dispatch($this->operationId, $lastContactId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(CrmBulkOperationRepositoryInterface::class)->markFailed($this->operationId);
    }
}
