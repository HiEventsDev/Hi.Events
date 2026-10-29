<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badges;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Badge\MarkBadgesPrintedRequest;
use HiEvents\Services\Application\Handlers\Badge\MarkBadgesPrintedHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MarkBadgesPrintedAction extends BaseAction
{
    public function __construct(
        private readonly MarkBadgesPrintedHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, MarkBadgesPrintedRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $updated = $this->handler->handle(
                eventId: $eventId,
                attendeeIds: array_map('intval', $request->validated('attendee_ids')),
            );
        } catch (ResourceNotFoundException $exception) {
            throw ValidationException::withMessages(['attendee_ids' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['updated' => $updated], wrapInData: true);
    }
}
