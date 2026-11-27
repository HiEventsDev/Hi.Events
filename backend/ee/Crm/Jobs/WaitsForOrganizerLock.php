<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use DateTimeInterface;

trait WaitsForOrganizerLock
{
    public int $timeout = 40;

    public int $maxExceptions = 3;

    public int $backoff = 30;

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    private function releaseUntilOrganizerUnlocked(): void
    {
        $this->release(random_int(2, 10));
    }
}
