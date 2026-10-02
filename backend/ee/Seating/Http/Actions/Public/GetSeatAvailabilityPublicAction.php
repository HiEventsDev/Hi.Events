<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions\Public;

use HiEvents\Enterprise\Seating\Resources\SeatAvailabilityResourcePublic;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\Public\GetSeatAvailabilityPublicHandler;
use HiEvents\Http\Actions\Events\BasePublicEventAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetSeatAvailabilityPublicAction extends BasePublicEventAction
{
    public function __construct(
        private readonly GetSeatAvailabilityPublicHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $occurrenceId): Response|JsonResponse
    {
        if (! $this->canUserViewEventWithId($eventId)) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(SeatAvailabilityResourcePublic::class, $this->handler->handle($eventId, $occurrenceId));
    }
}
