<?php

namespace HiEvents\Jobs\TicketResale;

use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Domain\TicketResale\ResaleRefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ReconcileResaleRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $listingId) {}

    /**
     * @throws Throwable
     */
    public function handle(ResaleRefundService $refundService, ChargeRefundUpdatedHandler $refundUpdatedHandler): void
    {
        $refund = $refundService->fetchStartedRefund($this->listingId);

        if ($refund !== null) {
            $refundUpdatedHandler->handleEvent($refund);
        }
    }
}
