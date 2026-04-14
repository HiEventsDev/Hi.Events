<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Resources\Message\OutgoingMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOutgoingMessagesAction extends BaseAction
{
    public function __construct(
        private readonly OutgoingMessageRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $params = $this->getPaginationQueryParams($request);

        $messages = $this->repository->getForEvent($eventId, $params);

        return $this->resourceResponse(OutgoingMessageResource::class, $messages);
    }
}
