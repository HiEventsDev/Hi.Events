<?php

namespace HiEvents\Console;

use HiEvents\Enterprise\Licensing\Console\GenerateLicenceKeypairCommand;
use HiEvents\Enterprise\Licensing\Console\IssueLicenceKeyCommand;
use HiEvents\Enterprise\Sso\Console\SsoVerifyDomainCommand;
use HiEvents\Enterprise\Sso\Jobs\RecheckSsoDomainsJob;
use HiEvents\Jobs\Abuse\ReportSuspiciousActivityJob;
use HiEvents\Jobs\Account\ProcessScheduledAccountDeletionsJob;
use HiEvents\Jobs\ApiKey\SendApiKeyExpiryRemindersJob;
use HiEvents\Jobs\Lifecycle\SendLifecycleNudgesJob;
use HiEvents\Jobs\Message\SendScheduledMessagesJob;
use HiEvents\Jobs\Waitlist\ProcessExpiredWaitlistOffersJob;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailSender;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        GenerateLicenceKeypairCommand::class,
        IssueLicenceKeyCommand::class,
        SsoVerifyDomainCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new SendScheduledMessagesJob)->everyMinute()->withoutOverlapping();
        $schedule->job(new ProcessExpiredWaitlistOffersJob)->everyMinute()->withoutOverlapping();
        $schedule->job(new ProcessScheduledAccountDeletionsJob)->hourly()->withoutOverlapping();
        $schedule->job(new RecheckSsoDomainsJob)->daily()->withoutOverlapping();
        $schedule->job(new SendApiKeyExpiryRemindersJob)->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('sanctum:prune-expired', ['--hours' => 24 * 30])->daily();
        $schedule->job(new ReportSuspiciousActivityJob)->everyFiveMinutes()->withoutOverlapping()->when(fn () => (bool) config('app.saas_mode_enabled'));
        $schedule->job(new SendLifecycleNudgesJob)->hourly()->withoutOverlapping()
            ->when(static fn (): bool => app(LifecycleEmailSender::class)->isConfigured());

        $schedule->call(function (): void {
            $count = DB::table('failed_jobs')->count();
            if ($count > 0) {
                Log::warning('Failed jobs present in queue', ['count' => $count]);
            }
        })->everyFiveMinutes()->name('failed-jobs-monitor')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        include base_path('routes/console.php');
    }
}
