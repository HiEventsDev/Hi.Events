<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events\Stats;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Event\EventCountsFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class GetEventCountsAction extends BaseAction
{
    public function __construct(
        private readonly EventCountsFetchService $eventCountsFetchService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            JsonResource::class,
            $this->eventCountsFetchService->getEventCounts($eventId),
        );
    }
}
