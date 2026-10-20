<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapRelabelRequiresConfirmationException;
use HiEvents\Enterprise\Seating\Http\Request\UpdateEventSeatMapLayoutRequest;
use HiEvents\Enterprise\Seating\Resources\EventSeatMapResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\UpdateEventSeatMapLayoutHandler;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;

class UpdateEventSeatMapLayoutAction extends BaseEventSeatMapAction
{
    public function __construct(
        private readonly UpdateEventSeatMapLayoutHandler $handler,
    ) {}

    public function __invoke(int $eventId, UpdateEventSeatMapLayoutRequest $request): JsonResponse
    {
        $this->authorizeEventSeatMapAccess($eventId);

        try {
            $eventSeatMap = $this->handler->handle(
                $eventId,
                $request->validated('layout'),
                $request->validated('version'),
                (bool) $request->validated('confirm_relabel'),
            );
        } catch (InvalidSeatMapLayoutException $exception) {
            throw $this->layoutValidationException($exception);
        } catch (SeatMapRelabelRequiresConfirmationException $exception) {
            return $this->jsonResponse([
                'message' => $exception->getMessage(),
                'errors' => [],
                'requires_relabel_confirmation' => true,
                'relabelled_seat_labels' => $exception->getSeatLabels(),
            ], ResponseCodes::HTTP_CONFLICT);
        } catch (SeatMapChangeConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(EventSeatMapResource::class, $eventSeatMap);
    }
}
