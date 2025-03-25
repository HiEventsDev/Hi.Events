<?php

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Exceptions\UnableToSendMessageException;
use HiEvents\Jobs\Event\SendEventEmailJob;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Log\Logger;

class SendEventEmailMessagesService
{
    private array $sentEmails = [];

    public function __construct(
        private readonly OrderRepositoryInterface    $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface    $eventRepository,
        private readonly MessageRepositoryInterface  $messageRepository,
        private readonly UserRepositoryInterface     $userRepository,
        private readonly Logger                      $logger,
        private readonly Dispatcher                  $dispatcher,
    )
    {
    }

    /**
     * @throws UnableToSendMessageException
     */
    public function send(SendMessageDTO $messageData): void
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                name: 'organizer'
            ))
            ->findById($messageData->event_id);

        $order = $this->orderRepository->findFirstWhere([
            'id' => $messageData->order_id,
            'event_id' => $messageData->event_id,
        ]);

        if ((!$order && $messageData->type === MessageTypeEnum::ORDER_OWNER) || !$messageData->id) {
            $message = 'Unable to send message. Order or message ID not present.';
            $this->logger->error($message, $messageData->toArray());
            $this->updateMessageStatus($messageData, MessageStatus::FAILED);

            throw new UnableToSendMessageException($message);
        }

        switch ($messageData->type) {
            case MessageTypeEnum::INDIVIDUAL_ATTENDEES:
                $this->sendAttendeeMessages($messageData, $event);
                break;
            case MessageTypeEnum::ORDER_OWNER:
                $this->sendOrderMessages($messageData, $event, $order);
                break;
            case MessageTypeEnum::TICKET_HOLDERS:
                $this->sendTicketHolderMessages($messageData, $event);
                break;
            case MessageTypeEnum::ALL_ATTENDEES:
                $this->sendEventMessages($messageData, $event);
                break;
            case MessageTypeEnum::ORDER_OWNERS_WITH_PRODUCT:
                $this->sendProductMessages($messageData, $event);
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

    private function sendTicketHolderMessages(SendMessageDTO $messageData, EventDomainObject $event): void
    {
        $attendees = $this->attendeeRepository->findWhereIn(
            field: 'product_id',
            values: $messageData->product_ids,
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
            fullName: $order->getFullName(),
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

        $sentEmails = [];
        $attendees->each(function (AttendeeDomainObject $attendee) use (&$sentEmails, $event, $messageData) {
            if (in_array($attendee->getEmail(), $sentEmails, true)) {
                return;
            }

            $sentEmails[] = $attendee->getEmail();

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

    private function sendProductMessages(SendMessageDTO $messageData, EventDomainObject $event): void
    {
        $orders = $this->orderRepository->findOrdersAssociatedWithProducts(
            eventId: $messageData->event_id,
            productIds: $messageData->product_ids,
            orderStatuses: $messageData->order_statuses
        );

        if ($orders->isEmpty()) {
            return;
        }

        $this->sendEmailToMessageSender($messageData, $event);

        $orders->each(function (OrderDomainObject $order) use ($messageData, $event) {
            $this->sendMessage(
                emailAddress: $order->getEmail(),
                fullName: $order->getFullName(),
                messageData: $messageData,
                event: $event,
            );
        });
    }

    private function sendMessage(
        string            $emailAddress,
        string            $fullName,
        SendMessageDTO    $messageData,
        EventDomainObject $event,
    ): void
    {
        if (in_array($emailAddress, $this->sentEmails, true)) {
            return;
        }

        $this->dispatcher->dispatch(
            new SendEventEmailJob(
                email: $emailAddress,
                toName: $fullName,
                eventMessage: new EventMessage(
                    event: $event,
                    eventSettings: $event->getEventSettings(),
                    messageData: $messageData
                ),
                messageData: $messageData,
            )
        );

        $this->sentEmails[] = $emailAddress;
    }
}
