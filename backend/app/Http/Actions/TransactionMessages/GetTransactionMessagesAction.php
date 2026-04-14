<?php

namespace HiEvents\Http\Actions\TransactionMessages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use HiEvents\Resources\TransactionMessage\OutgoingTransactionMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetTransactionMessagesAction extends BaseAction
{
    public function __construct(
        private readonly OutgoingTransactionMessageRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $params = $this->getPaginationQueryParams($request);

        $messages = $this->repository->getForEvent($eventId, $params);

        return $this->resourceResponse(OutgoingTransactionMessageResource::class, $messages);
    }
}
