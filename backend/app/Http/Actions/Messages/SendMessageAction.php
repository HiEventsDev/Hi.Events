<?php

namespace TicketKitten\Http\Actions\Messages;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\SendMessageDTO;
use TicketKitten\Http\Request\Message\SendMessageRequest;
use TicketKitten\Resources\Message\MessageResource;
use TicketKitten\Service\Handler\Message\SendMessageHandler;

class SendMessageAction extends BaseAction
{
    private SendMessageHandler $messageHandler;

    public function __construct(SendMessageHandler $messageHandler)
    {
        $this->messageHandler = $messageHandler;
    }

    public function __invoke(SendMessageRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $user = $this->getAuthenticatedUser();

        $message = $this->messageHandler->handle(SendMessageDTO::fromArray([
            'event_id' => $eventId,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'type' => $request->input('message_type'),
            'is_test' => $request->input('is_test'),
            'order_id' => $request->input('order_id'),
            'attendee_ids' => $request->input('attendee_ids'),
            'ticket_ids' => $request->input('ticket_ids'),
            'send_copy_to_current_user' => $request->boolean('send_copy_to_current_user'),
            'sent_by_user_id' => $user->getId(),
        ]));

        return $this->resourceResponse(MessageResource::class, $message);
    }
}
