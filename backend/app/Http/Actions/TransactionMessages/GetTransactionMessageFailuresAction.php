<?php

namespace HiEvents\Http\Actions\TransactionMessages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\TransactionMessage\OutgoingTransactionMessageResource;
use HiEvents\Services\Application\Handlers\TransactionMessage\GetTransactionMessageFailuresHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetTransactionMessageFailuresAction extends BaseAction
{
    public function __construct(
        private readonly GetTransactionMessageFailuresHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $params = $this->getPaginationQueryParams($request);

        $showResolved = $request->boolean('show_resolved', false);

        $failures = $this->handler->handle($eventId, $params, $showResolved);

        return $this->resourceResponse(OutgoingTransactionMessageResource::class, $failures);
    }
}
