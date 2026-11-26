<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class LaunchScheduledCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 40;

    public int $uniqueFor = 300;

    public function __construct(
        public int $campaignId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

    public function handle(CampaignLaunchService $launchService): void
    {
        try {
            $launchService->launchScheduled($this->campaignId, now());
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
