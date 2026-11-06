<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badges;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Badge\GetBadgesRequest;
use HiEvents\Repository\DTO\BadgeAttendeeQueryDTO;
use HiEvents\Resources\Badge\BadgeResource;
use HiEvents\Services\Application\Handlers\Badge\DTO\GetBadgesDTO;
use HiEvents\Services\Application\Handlers\Badge\GetBadgesHandler;
use Illuminate\Http\JsonResponse;

class GetBadgesAction extends BaseAction
{
    public function __construct(
        private readonly GetBadgesHandler $handler,
    ) {}

    public function __invoke(int $eventId, GetBadgesRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $badges = $this->handler->handle(new GetBadgesDTO(
            event_id: $eventId,
            attendee_ids: $request->idList('attendee_ids'),
            order_id: $request->validated('order_id') === null ? null : (int) $request->validated('order_id'),
            product_ids: $request->idList('product_ids'),
            event_occurrence_id: $request->validated('event_occurrence_id') === null ? null : (int) $request->validated('event_occurrence_id'),
            only_unprinted: $request->boolean('only_unprinted'),
            sort_by: $request->validated('sort_by') ?? BadgeAttendeeQueryDTO::SORT_LAST_NAME,
            page: (int) ($request->validated('page') ?? 1),
            per_page: (int) ($request->validated('per_page') ?? 100),
        ));

        return $this->resourceResponse(
            resource: BadgeResource::class,
            data: $badges,
        );
    }
}
