<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PAGE_SIZE = 1000;

    private const ORGANIZERS_PER_SECOND = 20;

    public int $timeout = 40;

    public function __construct(
        public int $afterStateId = 0,
    ) {}

    public function handle(CrmOrganizerStateRepositoryInterface $organizerStateRepository): void
    {
        $organizerIds = $organizerStateRepository->findOrganizerIdsAfter($this->afterStateId, self::PAGE_SIZE);
        $position = 0;

        foreach ($organizerIds as $organizerId) {
            ReconcileOrganizerContactsJob::dispatch($organizerId)
                ->delay(now()->addSeconds(intdiv($position++, self::ORGANIZERS_PER_SECOND)));
        }

        if ($organizerIds->count() === self::PAGE_SIZE) {
            self::dispatch((int) $organizerIds->keys()->last())
                ->delay(now()->addSeconds(intdiv(self::PAGE_SIZE, self::ORGANIZERS_PER_SECOND)));
        }
    }
}
