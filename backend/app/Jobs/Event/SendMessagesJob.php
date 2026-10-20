<?php

namespace HiEvents\Jobs\Event;

use HiEvents\Exceptions\UnableToSendMessageException;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Mail\SendEventEmailMessagesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly SendMessageDTO $messageData,
    ) {}

    /**
     * @throws UnableToSendMessageException
     */
    public function handle(SendEventEmailMessagesService $emailMessagesService): void
    {
        $emailMessagesService->send($this->messageData);
    }
}
