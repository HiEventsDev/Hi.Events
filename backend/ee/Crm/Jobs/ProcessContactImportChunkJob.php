<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmImportRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessContactImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 40;

    public function __construct(
        public int $importId,
        public ?int $tagId,
    ) {}

    public function handle(ContactImportRunner $runner): void
    {
        if ($runner->processNextChunk($this->importId, $this->tagId)) {
            self::dispatch($this->importId, $this->tagId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(CrmImportRepositoryInterface::class)->markFailed($this->importId);
    }
}
