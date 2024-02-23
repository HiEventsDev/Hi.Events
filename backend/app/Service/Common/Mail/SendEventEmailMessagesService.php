<?php

namespace TicketKitten\Service\Common\Mail;

use Illuminate\Mail\Mailer;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Log\Logger;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\Enums\MessageTypeEnum;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\DomainObjects\Status\AttendeeStatus;
use TicketKitten\DomainObjects\Status\MessageStatus;
use TicketKitten\Exceptions\UnableToSendMessageException;
use TicketKitten\Http\DataTransferObjects\SendMessageDTO;
use TicketKitten\Mail\EventMessage;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\MessageRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;

readonly class SendEventEmailMessagesService
{
    public function __construct(
        private OrderRepositoryInterface    $orderRepository,
        private AttendeeRepositoryInterface $attendeeRepository,
        private EventRepositoryInterface    $eventRepository,
        private MessageRepositoryInterface  $messageRepository,
        private UserRepositoryInterface     $userRepository,
        private Mailer                      $mailer,
        private Logger                      $logger
    )
    {
    }

    /**
     * @throws UnableToSendMessageException
     */
    public function send(SendMessageDTO $messageData): void
    {
        $event = $this->eventRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                name: 'organizer'
            ))
            ->findById($messageData->event_id);

        $order = $this->orderRepository->findFirstWhere([
            'id' => $messageData->order_id,
            'event_id' => $messageData->event_id,
        ]);

        if ((!$order && $messageData->type === MessageTypeEnum::ORDER) || !$messageData->id) {
            $message = 'Unable to send message. Order or message ID not present.';
            $this->logger->error($message, $messageData->toArray());
            $this->updateMessageStatus($messageData, MessageStatus::FAILED);

            throw new UnableToSendMessageException($message);
        }

        switch ($messageData->type) {
            case MessageTypeEnum::ATTENDEE:
                $this->sendAttendeeMessages($messageData, $event);
                break;
            case MessageTypeEnum::ORDER:
                $this->sendOrderMessages($messageData, $event, $order);
                break;
            case MessageTypeEnum::TICKET:
                $this->sendTicketMessages($messageData, $event);
                break;
            case MessageTypeEnum::EVENT:
                $this->sendEventMessages($messageData, $event);
                break;
        }

        $this->updateMessageStatus($messageData, MessageStatus::SENT);
    }

    private function sendAttendeeMessages(SendMessageDTO $messageData, EventDomainObject $event): void
    {
        $attendees = $this->attendeeRepository->findWhereIn(
            field: 'id',
            values: $messageData->attendee_ids,
            additionalWhere: [
                'event_id' => $messageData->event_id,
            ],
            columns: ['first_name', 'last_name', 'email']
        );

        $this->emailAttendees($attendees, $messageData, $event);
    }

    private function sendTicketMessages(SendMessageDTO $messageData, EventDomainObject $event): void
    {
        $attendees = $this->attendeeRepository->findWhereIn(
            field: 'ticket_id',
            values: $messageData->ticket_ids,
            additionalWhere: [
                'event_id' => $messageData->event_id,
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            columns: ['first_name', 'last_name', 'email']
        );

        $this->emailAttendees($attendees, $messageData, $event);
    }

    private function sendOrderMessages(
        SendMessageDTO    $messageData,
        EventDomainObject $event,
        OrderDomainObject $order,
    ): void
    {
        $this->sendEmailToMessageSender($messageData, $event);

        $this->sendMessage(
            emailAddress: $order->getEmail(),
            fullName: $order->getFirstName() . ' ' . $order->getLastName(),
            messageData: $messageData,
            event: $event,
        );
    }

    private function emailAttendees(
        Collection        $attendees,
        SendMessageDTO    $messageData,
        EventDomainObject $event,
    ): void
    {
        $this->sendEmailToMessageSender($messageData, $event);

        if ($messageData->is_test) {
            return;
        }

        $attendees->each(function (AttendeeDomainObject $attendee) use ($event, $messageData) {
            $this->sendMessage(
                emailAddress: $attendee->getEmail(),
                fullName: $attendee->getFullName(),
                messageData: $messageData,
                event: $event,
            );
        });
    }

    private function updateMessageStatus(SendMessageDTO $messageData, MessageStatus $status): void
    {
        $this->messageRepository->updateWhere(
            attributes: [
                'status' => $status->name,
            ],
            where: [
                'id' => $messageData->id,
            ]
        );
    }

    /**
     * @todo - Load test this. Events can have a lot of attendees.
     */
    private function sendEventMessages(SendMessageDTO $messageData, EventDomainObject $event): void
    {
        $attendees = $this->attendeeRepository->findWhere(
            where: [
                'event_id' => $messageData->event_id,
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            columns: ['first_name', 'last_name', 'email']
        );

        $this->emailAttendees($attendees, $messageData, $event);
    }

    private function sendEmailToMessageSender(SendMessageDTO $messageData, EventDomainObject $event): void
    {
        if (!$messageData->send_copy_to_current_user && !$messageData->is_test) {
            return;
        }

        $user = $this->userRepository->findById($messageData->sent_by_user_id);

        $this->sendMessage(
            emailAddress: $user->getEmail(),
            fullName: $user->getFullName(),
            messageData: $messageData,
            event: $event,
        );
    }

    private function sendMessage(string $emailAddress, string $fullName, SendMessageDTO $messageData, EventDomainObject $event): void
    {
        $this->mailer->to(
            $emailAddress,
            $fullName
        )
            ->queue(new EventMessage($event, $messageData));
    }
}
