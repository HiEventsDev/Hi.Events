<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Exceptions\MessagingTierLimitExceededException;
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
            $validated = $request->validated();

            $message = $this->messageHandler->handle(SendMessageDTO::fromArray([
                'event_id' => $eventId,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'type' => $validated['message_type'],
                'is_test' => (bool) ($validated['is_test'] ?? false),
                'order_id' => $validated['order_id'] ?? null,
                'attendee_ids' => $validated['attendee_ids'] ?? [],
                'product_ids' => $validated['product_ids'] ?? [],
                'order_statuses' => $validated['order_statuses'] ?? [],
                'send_copy_to_current_user' => (bool) ($validated['send_copy_to_current_user'] ?? false),
                'sent_by_user_id' => $user->getId(),
                'account_id' => $this->getAuthenticatedAccountId(),
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'event_occurrence_id' => $validated['event_occurrence_id'] ?? null,
                'event_occurrence_ids' => $validated['event_occurrence_ids'] ?? null,
            ]));
        } catch (AccountNotVerifiedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (MessagingTierLimitExceededException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $this->resourceResponse(MessageResource::class, $message);
    }
}
