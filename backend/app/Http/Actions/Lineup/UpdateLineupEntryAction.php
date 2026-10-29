<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\UpdateLineupEntryRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Lineup\LineupEntryResource;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertLineupEntryDTO;
use HiEvents\Services\Application\Handlers\Lineup\UpsertLineupEntryHandler;
use Illuminate\Http\JsonResponse;

class UpdateLineupEntryAction extends BaseAction
{
    public function __construct(
        private readonly UpsertLineupEntryHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $lineupEntryId, UpdateLineupEntryRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $entry = $this->handler->handle(new UpsertLineupEntryDTO(
            event_id: $eventId,
            billing_tier: LineupBillingTier::from($request->validated('billing_tier')),
            role_label: $request->validated('role_label'),
            is_hidden: $request->has('is_hidden') ? $request->boolean('is_hidden') : null,
            lineup_entry_id: $lineupEntryId,
        ));

        return $this->resourceResponse(
            resource: LineupEntryResource::class,
            data: $entry,
            statusCode: ResponseCodes::HTTP_OK,
        );
    }
}
