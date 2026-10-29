<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Resources\SeatMapResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\GetSeatMapHandler;
use Illuminate\Http\JsonResponse;

class GetSeatMapAction extends BaseSeatMapAction
{
    public function __construct(
        private readonly GetSeatMapHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $seatMapId): JsonResponse
    {
        $this->authorizeSeatMapAccess($organizerId);

        return $this->resourceResponse(
            resource: SeatMapResource::class,
            data: $this->handler->handle($seatMapId, $organizerId, $this->getAuthenticatedAccountId()),
        );
    }
}
