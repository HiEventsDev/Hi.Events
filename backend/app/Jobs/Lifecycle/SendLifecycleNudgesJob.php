<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Lifecycle;

use HiEvents\Services\Domain\LifecycleEmail\LifecycleNudgeSweepService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLifecycleNudgesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 55;

    public int $tries = 1;

    public function handle(LifecycleNudgeSweepService $sweepService): void
    {
        $sweepService->sweep();
    }
}
