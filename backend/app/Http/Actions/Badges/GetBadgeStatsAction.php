<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badges;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Badge\BadgeStatsResource;
use HiEvents\Services\Application\Handlers\Badge\GetBadgeStatsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetBadgeStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetBadgeStatsHandler $handler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $request->validate([
            'event_occurrence_id' => ['nullable', 'integer'],
        ]);

        $occurrenceId = $request->query('event_occurrence_id');

        return $this->resourceResponse(
            resource: BadgeStatsResource::class,
            data: $this->handler->handle($eventId, $occurrenceId === null ? null : (int) $occurrenceId),
        );
    }
}
