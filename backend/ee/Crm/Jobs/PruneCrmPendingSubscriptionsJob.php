<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmPendingSubscriptionRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneCrmPendingSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const KEEP_EXPIRED_FOR_DAYS = 30;

    public int $timeout = 40;

    public function handle(CrmPendingSubscriptionRepositoryInterface $pendingRepository): void
    {
        $pendingRepository->pruneUnconfirmedExpiredBefore(now()->subDays(self::KEEP_EXPIRED_FOR_DAYS));
    }
}
