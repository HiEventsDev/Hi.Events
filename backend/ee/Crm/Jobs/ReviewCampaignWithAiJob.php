<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignAutoReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewCampaignWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    /**
     * @var int[]
     */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $campaignId,
        private readonly int $recipientsCount,
        private readonly string $reviewReason,
    ) {}

    public function handle(CampaignAutoReviewService $autoReviewService): void
    {
        $autoReviewService->review($this->campaignId, $this->recipientsCount, $this->reviewReason);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Campaign AI review did not complete, handing to a person', [
            'campaignId' => $this->campaignId,
            'exception' => $exception?->getMessage(),
        ]);

        app(CampaignAutoReviewService::class)->handOverUnreviewed($this->campaignId, $this->recipientsCount, $this->reviewReason);
    }
}
