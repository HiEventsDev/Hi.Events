<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Message;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Domain\Message\MessageDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendScheduledMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        MessageRepositoryInterface $messageRepository,
        MessageDispatchService     $messageDispatchService,
    ): void
    {
        $messages = $messageRepository->findWhere([
            'status' => MessageStatus::SCHEDULED->name,
            ['scheduled_at', '<=', Carbon::now()->toDateTimeString()],
        ]);

        foreach ($messages as $message) {
            try {
                $messageDispatchService->dispatchMessage($message);
            } catch (Throwable $e) {
                Log::error('Failed to dispatch scheduled message', [
                    'message_id' => $message->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
