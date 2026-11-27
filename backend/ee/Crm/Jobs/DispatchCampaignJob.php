<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCampaignJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 40;

    public int $uniqueFor = 300;

    public function __construct(
        public int $campaignId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

    public function handle(CampaignDispatchService $dispatchService): void
    {
        $dispatchService->dispatch($this->campaignId);
    }
}
