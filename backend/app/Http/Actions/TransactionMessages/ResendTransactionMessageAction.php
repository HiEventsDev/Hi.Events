<?php

namespace HiEvents\Http\Actions\TransactionMessages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\TransactionMessage\OutgoingTransactionMessageResource;
use HiEvents\Services\Application\Handlers\TransactionMessage\ResendTransactionMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResendTransactionMessageAction extends BaseAction
{
    public function __construct(
        private readonly ResendTransactionMessageHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $messageId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $request->validate([
            'email' => 'sometimes|nullable|email',
        ]);

        try {
            $message = $this->handler->handle(
                eventId: $eventId,
                messageId: $messageId,
                newEmail: $request->input('email'),
            );

            return $this->resourceResponse(OutgoingTransactionMessageResource::class, $message);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
