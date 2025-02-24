<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Message\SendMessageRequest;
use HiEvents\Resources\Message\MessageResource;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Application\Handlers\Message\SendMessageHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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

        try {
            $message = $this->messageHandler->handle(SendMessageDTO::fromArray([
                'event_id' => $eventId,
                'subject' => $request->input('subject'),
                'message' => $request->input('message'),
                'type' => $request->input('message_type'),
                'is_test' => $request->input('is_test'),
                'order_id' => $request->input('order_id'),
                'attendee_ids' => $request->input('attendee_ids'),
                'product_ids' => $request->input('product_ids'),
                'order_statuses' => $request->input('order_statuses'),
                'send_copy_to_current_user' => $request->boolean('send_copy_to_current_user'),
                'sent_by_user_id' => $user->getId(),
                'account_id' => $this->getAuthenticatedAccountId(),
            ]));
        } catch (AccountNotVerifiedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }

        return $this->resourceResponse(MessageResource::class, $message);
    }
}
