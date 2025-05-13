<?php

namespace HiEvents\Jobs\Event;

use HiEvents\DomainObjects\Generated\OutgoingMessageDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OutgoingMessageStatus;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendEventEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string         $email,
        private readonly string         $toName,
        private readonly EventMessage   $eventMessage,
        private readonly SendMessageDTO $messageData,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(
        Mailer                             $mailer,
        OutgoingMessageRepositoryInterface $outgoingMessageRepository,
    ): void
    {
        try {
            $mailer
                ->to($this->email, $this->toName)
                ->send($this->eventMessage);
        } catch (Throwable $exception) {
            $outgoingMessageRepository->create([
                OutgoingMessageDomainObjectAbstract::MESSAGE_ID => $this->messageData->id,
                OutgoingMessageDomainObjectAbstract::EVENT_ID => $this->messageData->event_id,
                OutgoingMessageDomainObjectAbstract::STATUS => OutgoingMessageStatus::FAILED->name,
                OutgoingMessageDomainObjectAbstract::RECIPIENT => $this->email,
                OutgoingMessageDomainObjectAbstract::SUBJECT => $this->messageData->subject,
            ]);

            throw $exception;
        }

        $outgoingMessageRepository->create([
            OutgoingMessageDomainObjectAbstract::MESSAGE_ID => $this->messageData->id,
            OutgoingMessageDomainObjectAbstract::EVENT_ID => $this->messageData->event_id,
            OutgoingMessageDomainObjectAbstract::STATUS => OutgoingMessageStatus::SENT->name,
            OutgoingMessageDomainObjectAbstract::RECIPIENT => $this->email,
            OutgoingMessageDomainObjectAbstract::SUBJECT => $this->messageData->subject,
        ]);
    }
}
