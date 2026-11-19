<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactReconcileService;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\DTO\ReconcileWindowDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileOrganizerContactsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 40;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $organizerId,
        public ?ReconcileWindowDTO $window = null,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->organizerId;
    }

    public function handle(ContactReconcileService $reconcileService): void
    {
        $remaining = $reconcileService->reconcile($this->organizerId, $this->window);

        if ($remaining !== null) {
            self::dispatch($this->organizerId, $remaining);
        }
    }
}
