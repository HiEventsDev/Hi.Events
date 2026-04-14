<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Message\OutgoingMessageResource;
use HiEvents\Services\Application\Handlers\Message\ResendOutgoingMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResendOutgoingMessageAction extends BaseAction
{
    public function __construct(
        private readonly ResendOutgoingMessageHandler $handler,
    )
    {
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function __invoke(Request $request, int $eventId, int $messageId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->validate($request, [
            'email' => 'sometimes|email',
        ]);

        $result = $this->handler->handle(
            eventId: $eventId,
            messageId: $messageId,
            newEmail: $request->input('email'),
        );

        return $this->resourceResponse(OutgoingMessageResource::class, $result);
    }
}
