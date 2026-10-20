<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Event\UpdateEventLocationRequest;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventLocationDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventLocationHandler;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use Illuminate\Http\JsonResponse;
use Throwable;

class UpdateEventLocationAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventLocationHandler $handler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(UpdateEventLocationRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $eventLocationPayload = $request->validated('event_location');

        $event = $this->handler->handle(new UpdateEventLocationDTO(
            event_id: $eventId,
            account_id: $this->getAuthenticatedAccountId(),
            event_location: $eventLocationPayload !== null
                ? EventLocationData::fromArray($eventLocationPayload)
                : null,
            clear_event_location: (bool) $request->validated('clear_event_location', false),
        ));

        return $this->resourceResponse(EventResource::class, $event);
    }
}
