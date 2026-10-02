<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Http\Request\UpsertSeatMapRequest;
use HiEvents\Enterprise\Seating\Resources\SeatMapResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\CreateSeatMapHandler;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;

class CreateSeatMapAction extends BaseSeatMapAction
{
    public function __construct(
        private readonly CreateSeatMapHandler $handler,
    ) {}

    public function __invoke(int $organizerId, UpsertSeatMapRequest $request): JsonResponse
    {
        $this->authorizeSeatMapSetup($organizerId);

        try {
            $seatMap = $this->handler->handle($this->upsertDtoFromRequest($organizerId, $request));
        } catch (InvalidSeatMapLayoutException $exception) {
            throw $this->layoutValidationException($exception);
        }

        return $this->resourceResponse(
            resource: SeatMapResource::class,
            data: $seatMap,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
