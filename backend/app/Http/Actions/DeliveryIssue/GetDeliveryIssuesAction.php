<?php

namespace HiEvents\Http\Actions\DeliveryIssue;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\DeliveryIssue\DeliveryIssueResource;
use HiEvents\Services\Application\Handlers\DeliveryIssue\GetDeliveryIssuesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetDeliveryIssuesAction extends BaseAction
{
    public function __construct(
        private readonly GetDeliveryIssuesHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $params = $this->getPaginationQueryParams($request);
        $showResolved = $request->boolean('show_resolved', false);

        $results = $this->handler->handle(
            eventId: $eventId,
            perPage: $params->per_page ?? 20,
            page: $params->page ?? 1,
            showResolved: $showResolved,
        );

        return $this->resourceResponse(DeliveryIssueResource::class, $results);
    }
}
