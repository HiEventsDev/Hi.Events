<?php

namespace HiEvents\Service\Handler\Message;

use Carbon\Carbon;
use HTMLPurifier;
use Illuminate\Support\Collection;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Http\DataTransferObjects\SendMessageDTO;
use HiEvents\Jobs\SendMessagesJob;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;

readonly class SendMessageHandler
{
    public function __construct(
        private OrderRepositoryInterface    $orderRepository,
        private AttendeeRepositoryInterface $attendeeRepository,
        private TicketRepositoryInterface   $ticketRepository,
        private MessageRepositoryInterface  $messageRepository,
        private HTMLPurifier                $purifier,
    )
    {
    }

    public function handle(SendMessageDTO $messageData): MessageDomainObject
    {
        $message = $this->messageRepository->create([
            'event_id' => $messageData->event_id,
            'subject' => $messageData->subject,
            'message' => $this->purifier->purify($messageData->message),
            'type' => $messageData->type->name,
            'order_id' => $this->getOrderId($messageData),
            'attendee_ids' => $this->getAttendeeIds($messageData)->toArray(),
            'ticket_ids' => $this->getTicketIds($messageData)->toArray(),
            'sent_at' => Carbon::now()->toDateTimeString(),
            'sent_by_user_id' => $messageData->sent_by_user_id,
            'status' => MessageStatus::PROCESSING->name,
        ]);

        $updatedData = SendMessageDTO::fromArray([
            'id' => $message->getId(),
            'event_id' => $messageData->event_id,
            'subject' => $messageData->subject,
            'message' => $this->purifier->purify($messageData->message),
            'type' => $messageData->type,
            'is_test' => $messageData->is_test,
            'order_id' => $message->getOrderId(),
            'attendee_ids' => $message->getAttendeeIds(),
            'ticket_ids' => $message->getTicketIds(),
            'send_copy_to_current_user' => $messageData->send_copy_to_current_user,
            'sent_by_user_id' => $messageData->sent_by_user_id,
        ]);

        SendMessagesJob::dispatch($updatedData);

        return $message;
    }

    private function getAttendeeIds(SendMessageDTO $messageData): Collection
    {
        $attendees = $this->attendeeRepository->findWhereIn(
            field: 'id',
            values: $messageData->attendee_ids,
            additionalWhere: [
                'event_id' => $messageData->event_id,
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            columns: ['id']
        );

        return $attendees->map(fn($attendee) => $attendee->getId());
    }


    private function getTicketIds(SendMessageDTO $messageData): Collection
    {
        $tickets = $this->ticketRepository->findWhereIn(
            field: 'id',
            values: $messageData->ticket_ids,
            additionalWhere: [
                'event_id' => $messageData->event_id,
            ],
            columns: ['id']
        );

        return $tickets->map(fn($attendee) => $attendee->getId());
    }

    private function getOrderId(SendMessageDTO $messageData): ?int
    {
        return $this->orderRepository->findFirstWhere([
            'id' => $messageData->order_id,
            'event_id' => $messageData->event_id,
        ])?->getId();
    }
}
