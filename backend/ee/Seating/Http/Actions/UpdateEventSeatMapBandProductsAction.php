<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Http\Request\UpdateEventSeatMapBandProductsRequest;
use HiEvents\Enterprise\Seating\Resources\EventSeatMapResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductsDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\UpdateEventSeatMapBandProductsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateEventSeatMapBandProductsAction extends BaseEventSeatMapAction
{
    public function __construct(
        private readonly UpdateEventSeatMapBandProductsHandler $handler,
    ) {}

    public function __invoke(int $eventId, UpdateEventSeatMapBandProductsRequest $request): JsonResponse
    {
        $this->authorizeEventSeatMapAccess($eventId);

        try {
            $eventSeatMap = $this->handler->handle(
                $eventId,
                collect($request->validated('band_products'))->map(fn (array $band) => BandProductsDTO::from($band)),
            );
        } catch (SeatMapChangeConflictException $exception) {
            throw ValidationException::withMessages(['band_products' => $exception->getMessage()]);
        }

        return $this->resourceResponse(EventSeatMapResource::class, $eventSeatMap);
    }
}
