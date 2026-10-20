<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DetachEventSeatMapHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DetachEventSeatMapAction extends BaseSeatingAction
{
    public function __construct(
        private readonly DetachEventSeatMapHandler $handler,
    ) {}

    public function __invoke(int $eventId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->handler->handle($eventId);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->deletedResponse();
    }
}
