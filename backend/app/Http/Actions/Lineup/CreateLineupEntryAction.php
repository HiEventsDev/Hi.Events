<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\CreateLineupEntryRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Lineup\LineupEntryResource;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertLineupEntryDTO;
use HiEvents\Services\Application\Handlers\Lineup\UpsertLineupEntryHandler;
use Illuminate\Http\JsonResponse;

class CreateLineupEntryAction extends BaseAction
{
    public function __construct(
        private readonly UpsertLineupEntryHandler $handler,
    ) {}

    public function __invoke(int $eventId, CreateLineupEntryRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $entry = $this->handler->handle(new UpsertLineupEntryDTO(
                event_id: $eventId,
                billing_tier: LineupBillingTier::from($request->validated('billing_tier')),
                role_label: $request->validated('role_label'),
                is_hidden: $request->boolean('is_hidden'),
                profile_id: $request->integer('profile_id'),
            ));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: LineupEntryResource::class,
            data: $entry,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
