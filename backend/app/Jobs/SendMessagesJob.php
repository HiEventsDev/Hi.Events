<?php

namespace TicketKitten\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use TicketKitten\Exceptions\UnableToSendMessageException;
use TicketKitten\Http\DataTransferObjects\SendMessageDTO;
use TicketKitten\Service\Common\Mail\SendEventEmailMessagesService;

class SendMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private SendMessageDTO $messageData;

    public function __construct(SendMessageDTO $messageData)
    {
        $this->messageData = $messageData;
    }

    /**
     * @throws UnableToSendMessageException
     */
    public function handle(SendEventEmailMessagesService $emailMessagesService): void
    {
        $emailMessagesService->send($this->messageData);
    }
}
