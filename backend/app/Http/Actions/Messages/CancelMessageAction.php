<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Message\MessageResource;
use HiEvents\Services\Application\Handlers\Message\CancelMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelMessageAction extends BaseAction
{
    public function __construct(
        private readonly CancelMessageHandler $cancelMessageHandler,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $messageId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $message = $this->cancelMessageHandler->handle($messageId, $eventId);

        return $this->resourceResponse(MessageResource::class, $message);
    }
}
