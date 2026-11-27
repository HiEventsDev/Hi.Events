<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Message;

use HiEvents\Services\Domain\Message\Review\MessageAutoReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewMessageWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    /**
     * @var int[]
     */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $messageId,
        public readonly int $recipientsCount,
    ) {}

    public function handle(MessageAutoReviewService $autoReviewService): void
    {
        $autoReviewService->review($this->messageId, $this->recipientsCount);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Message AI review did not complete, handing to a person', [
            'messageId' => $this->messageId,
            'exception' => $exception?->getMessage(),
        ]);

        app(MessageAutoReviewService::class)->handOverUnreviewed($this->messageId);
    }
}
