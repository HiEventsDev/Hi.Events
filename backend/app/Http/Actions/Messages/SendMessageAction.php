<?php

namespace HiEvents\Http\Actions\Messages;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\SendMessageDTO;
use HiEvents\Http\Request\Message\SendMessageRequest;
use HiEvents\Resources\Message\MessageResource;
use HiEvents\Service\Handler\Message\SendMessageHandler;

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
