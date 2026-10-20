<?php

namespace HiEvents\Jobs\Event;

use HiEvents\DomainObjects\Generated\OutgoingMessageDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OutgoingMessageStatus;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Email\Delivery\EmailProviderMessageIdResolver;
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
        private readonly string $email,
        private readonly string $toName,
        private readonly EventMessage $eventMessage,
        private readonly SendMessageDTO $messageData,
    ) {}

    public function handle(
        Mailer $mailer,
        OutgoingMessageRepositoryInterface $outgoingMessageRepository,
        EmailProviderMessageIdResolver $providerMessageIdResolver,
    ): void {
        $sentMessage = $mailer
            ->to($this->email, $this->toName)
            ->sendNow($this->eventMessage);

        try {
            $outgoingMessageRepository->create($this->outgoingMessageAttributes(
                OutgoingMessageStatus::SENT,
                $sentMessage === null
                    ? null
                    : $providerMessageIdResolver->resolve($sentMessage->getSymfonySentMessage()),
            ));
        } catch (Throwable $exception) {
            logger()?->error('Failed to record sent event email', [
                'message_id' => $this->messageData->id,
                'recipient' => $this->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(OutgoingMessageRepositoryInterface::class)->create(
            $this->outgoingMessageAttributes(OutgoingMessageStatus::FAILED, null),
        );
    }

    private function outgoingMessageAttributes(OutgoingMessageStatus $status, ?string $providerMessageId): array
    {
        return [
            OutgoingMessageDomainObjectAbstract::MESSAGE_ID => $this->messageData->id,
            OutgoingMessageDomainObjectAbstract::EVENT_ID => $this->messageData->event_id,
            OutgoingMessageDomainObjectAbstract::STATUS => $status->name,
            OutgoingMessageDomainObjectAbstract::RECIPIENT => $this->email,
            OutgoingMessageDomainObjectAbstract::SUBJECT => $this->messageData->subject,
            OutgoingMessageDomainObjectAbstract::PROVIDER_MESSAGE_ID => $providerMessageId,
        ];
    }
}
