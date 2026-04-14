<?php

namespace HiEvents\Services\Application\Handlers\Message;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OutgoingMessageDomainObjectAbstract;
use HiEvents\DomainObjects\OutgoingMessageDomainObject;
use HiEvents\DomainObjects\Status\OutgoingMessageStatus;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use Illuminate\Mail\Mailer;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResendOutgoingMessageHandler
{
    public function __construct(
        private readonly OutgoingMessageRepositoryInterface $outgoingMessageRepository,
        private readonly MessageRepositoryInterface         $messageRepository,
        private readonly EventRepositoryInterface           $eventRepository,
        private readonly EmailSuppressionService            $suppressionService,
        private readonly Mailer                             $mailer,
    )
    {
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(int $eventId, int $messageId, ?string $newEmail = null): OutgoingMessageDomainObject
    {
        $outgoingMessage = $this->outgoingMessageRepository->findFirstWhere([
            OutgoingMessageDomainObjectAbstract::ID => $messageId,
            OutgoingMessageDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$outgoingMessage) {
            throw ValidationException::withMessages(['message' => [__('Outgoing message not found')]]);
        }

        $message = $this->messageRepository->findById($outgoingMessage->getMessageId());

        $event = $this->eventRepository
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($eventId);

        $recipient = $newEmail ? strtolower($newEmail) : $outgoingMessage->getRecipient();

        try {
            $type = MessageTypeEnum::fromName($message->getType());
        } catch (\Throwable) {
            $type = MessageTypeEnum::ALL_ATTENDEES;
        }

        $messageDTO = new SendMessageDTO(
            account_id: $event->getAccountId(),
            event_id: $eventId,
            subject: $message->getSubject(),
            message: $message->getMessage(),
            type: $type,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: $message->getSentByUserId(),
            id: $message->getId(),
        );

        $mail = new EventMessage($event, $event->getEventSettings(), $messageDTO);

        if ($outgoingMessage->getSesMessageId()) {
            $mail->retryForSesMessageId = $outgoingMessage->getSesMessageId();
        }

        if ($this->suppressionService->isEmailSuppressed($recipient, $event->getAccountId(), 'marketing')) {
            $this->outgoingMessageRepository->create([
                OutgoingMessageDomainObjectAbstract::MESSAGE_ID => $message->getId(),
                OutgoingMessageDomainObjectAbstract::EVENT_ID => $eventId,
                OutgoingMessageDomainObjectAbstract::STATUS => OutgoingMessageStatus::SUPPRESSED->name,
                OutgoingMessageDomainObjectAbstract::RECIPIENT => $recipient,
                OutgoingMessageDomainObjectAbstract::SUBJECT => $message->getSubject(),
                OutgoingMessageDomainObjectAbstract::RETRY_FOR_ID => $outgoingMessage->getId(),
            ]);

            return $this->outgoingMessageRepository->findById($messageId);
        }

        try {
            $sentMessage = $this->mailer->to($recipient)->sendNow($mail);
        } catch (Throwable $e) {
            $this->outgoingMessageRepository->create([
                OutgoingMessageDomainObjectAbstract::MESSAGE_ID => $message->getId(),
                OutgoingMessageDomainObjectAbstract::EVENT_ID => $eventId,
                OutgoingMessageDomainObjectAbstract::STATUS => OutgoingMessageStatus::FAILED->name,
                OutgoingMessageDomainObjectAbstract::RECIPIENT => $recipient,
                OutgoingMessageDomainObjectAbstract::SUBJECT => $message->getSubject(),
                OutgoingMessageDomainObjectAbstract::RETRY_FOR_ID => $outgoingMessage->getId(),
            ]);

            throw $e;
        }

        $this->outgoingMessageRepository->create([
            OutgoingMessageDomainObjectAbstract::MESSAGE_ID => $message->getId(),
            OutgoingMessageDomainObjectAbstract::EVENT_ID => $eventId,
            OutgoingMessageDomainObjectAbstract::STATUS => OutgoingMessageStatus::SENT->name,
            OutgoingMessageDomainObjectAbstract::RECIPIENT => $recipient,
            OutgoingMessageDomainObjectAbstract::SUBJECT => $message->getSubject(),
            OutgoingMessageDomainObjectAbstract::SES_MESSAGE_ID => $sentMessage?->getMessageId(),
            OutgoingMessageDomainObjectAbstract::RETRY_FOR_ID => $outgoingMessage->getId(),
        ]);

        $this->outgoingMessageRepository->updateWhere(
            attributes: [
                OutgoingMessageDomainObjectAbstract::RESOLVED_AT => now()->toDateTimeString(),
                OutgoingMessageDomainObjectAbstract::RESOLUTION_TYPE => 'auto',
            ],
            where: [OutgoingMessageDomainObjectAbstract::ID => $outgoingMessage->getId()],
        );

        return $this->outgoingMessageRepository->findById($messageId);
    }
}
