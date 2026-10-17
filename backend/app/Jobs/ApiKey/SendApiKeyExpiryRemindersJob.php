<?php

declare(strict_types=1);

namespace HiEvents\Jobs\ApiKey;

use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyExpiryReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendApiKeyExpiryRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ApiKeyExpiryReminderService $reminderService): void
    {
        $reminderService->sendDueReminders();
    }
}
