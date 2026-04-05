<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Services\Application\Handlers\Event\GetAllPublicEventsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetEventsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetAllPublicEventsHandler $handler,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $events = $this->handler->handle(
            queryParams: $this->getPaginationQueryParams($request),
        );

        return $this->resourceResponse(
            resource: EventResourcePublic::class,
            data: $events,
        );
    }
}
