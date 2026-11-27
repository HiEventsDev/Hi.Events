<?php

namespace HiEvents\Jobs\TicketResale;

use HiEvents\DomainObjects\Enums\ResaleRefundOutcome;
use HiEvents\Services\Domain\TicketResale\ResaleRefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessResaleRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 12;

    public function __construct(private readonly int $listingId) {}

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 300, 600, 900, 1800, 3600];
    }

    /**
     * @throws Throwable
     */
    public function handle(ResaleRefundService $refundService): void
    {
        if ($refundService->process($this->listingId, $this->attempts()) === ResaleRefundOutcome::NOT_READY) {
            $this->release($this->backoff()[min($this->attempts(), count($this->backoff())) - 1]);
        }
    }

    public function failed(): void
    {
        app(ResaleRefundService::class)->markFailedIfNotStarted($this->listingId);
    }
}
