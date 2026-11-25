<?php

namespace HiEvents\Console\Commands;

use Carbon\Carbon;
use HiEvents\Jobs\TicketResale\ProcessResaleRefundJob;
use HiEvents\Jobs\TicketResale\ReconcileResaleRefundJob;
use HiEvents\Repository\Interfaces\SelfServiceVerificationRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\TicketResale\TicketResaleClosureService;
use Illuminate\Console\Command;

class MaintainTicketResaleCommand extends Command
{
    private const BATCH_SIZE = 500;

    private const REFUND_REDISPATCH_AFTER_MINUTES = 75;

    private const REFUND_RECONCILE_AFTER_MINUTES = 60;

    private const VERIFICATION_RETENTION_DAYS = 1;

    protected $signature = 'ticket-resale:maintain';

    protected $description = 'Close resale listings that can no longer sell, restart resale refunds that never started, settle refunds Stripe never reported back and prune expired self-service verifications';

    public function handle(
        TicketResaleClosureService $closureService,
        TicketResaleListingRepositoryInterface $listingRepository,
        SelfServiceVerificationRepositoryInterface $verificationRepository,
    ): int {
        $closed = $closureService->closeLapsed(self::BATCH_SIZE);

        $refundListingIds = $listingRepository->touchRefundsNotStartedSince(
            Carbon::now()->subMinutes(self::REFUND_REDISPATCH_AFTER_MINUTES)->toDateTimeString(),
            self::BATCH_SIZE,
        );

        foreach ($refundListingIds as $listingId) {
            ProcessResaleRefundJob::dispatch($listingId);
        }

        $reconcileListingIds = $listingRepository->touchRefundsAwaitingStripeSince(
            Carbon::now()->subMinutes(self::REFUND_RECONCILE_AFTER_MINUTES)->toDateTimeString(),
            self::BATCH_SIZE,
        );

        foreach ($reconcileListingIds as $listingId) {
            ReconcileResaleRefundJob::dispatch($listingId);
        }

        $pruned = $verificationRepository->deleteExpiredBefore(
            Carbon::now()->subDays(self::VERIFICATION_RETENTION_DAYS)->toDateTimeString(),
        );

        $this->info(sprintf(
            'Closed %d listing(s), restarted %d refund(s), checked %d refund(s) with Stripe, pruned %d verification(s).',
            $closed,
            count($refundListingIds),
            count($reconcileListingIds),
            $pruned,
        ));

        return self::SUCCESS;
    }
}
