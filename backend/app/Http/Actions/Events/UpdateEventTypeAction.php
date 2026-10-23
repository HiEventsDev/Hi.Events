<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CannotChangeEventTypeException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Event\UpdateEventTypeRequest;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventTypeDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventTypeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateEventTypeAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventTypeHandler $handler,
    ) {}

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(UpdateEventTypeRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $event = $this->handler->handle(new UpdateEventTypeDTO(
                event_id: $eventId,
                account_id: $this->getAuthenticatedAccountId(),
                type: EventType::fromName($request->validated('type')),
            ));
        } catch (CannotChangeEventTypeException $exception) {
            throw ValidationException::withMessages([
                'type' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(EventResource::class, $event);
    }
}
