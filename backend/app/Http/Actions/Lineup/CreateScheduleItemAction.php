<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\UpsertScheduleItemRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Lineup\ScheduleItemResource;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertScheduleItemDTO;
use HiEvents\Services\Application\Handlers\Lineup\UpsertScheduleItemHandler;
use Illuminate\Http\JsonResponse;

class CreateScheduleItemAction extends BaseAction
{
    public function __construct(
        private readonly UpsertScheduleItemHandler $handler,
    ) {}

    public function __invoke(int $eventId, UpsertScheduleItemRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $item = $this->handler->handle(new UpsertScheduleItemDTO(
            event_id: $eventId,
            starts_at: $request->validated('starts_at'),
            ends_at: $request->validated('ends_at'),
            title: $request->validated('title'),
            description: $request->validated('description'),
            event_space_id: $request->validated('event_space_id'),
            lineup_entry_ids: array_map('intval', $request->validated('lineup_entry_ids') ?? []),
        ));

        return $this->resourceResponse(
            resource: ScheduleItemResource::class,
            data: $item,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
