<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Message\OutgoingMessageResource;
use HiEvents\Services\Application\Handlers\Message\GetMessageRecipientsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetMessageRecipientsAction extends BaseAction
{
    public function __construct(
        private readonly GetMessageRecipientsHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $messageId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $params = $this->getPaginationQueryParams($request);

        $recipients = $this->handler->handle($eventId, $messageId, $params);

        return $this->resourceResponse(OutgoingMessageResource::class, $recipients);
    }
}
