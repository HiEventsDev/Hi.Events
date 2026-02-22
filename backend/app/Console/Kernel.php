<?php

namespace HiEvents\Console;

use HiEvents\Jobs\Message\SendScheduledMessagesJob;
use HiEvents\Jobs\Waitlist\ProcessExpiredWaitlistOffersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new SendScheduledMessagesJob)->everyMinute()->withoutOverlapping();
        $schedule->job(new ProcessExpiredWaitlistOffersJob)->everyMinute()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        include base_path('routes/console.php');
    }
}
