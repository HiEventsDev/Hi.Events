<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\SeatingCharts;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\SeatRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignSeatAction extends BaseAction
{
    public function __construct(
        private readonly SeatRepositoryInterface $seatRepository,
    )
    {
    }

    public function __invoke(int $eventId, int $seatingChartId, int $seatId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $request->validate([
            'attendee_id' => 'required|integer',
            'product_id' => 'nullable|integer',
        ]);

        $seat = $this->seatRepository->findFirstWhere([
            'id' => $seatId,
            'chart_id' => $seatingChartId,
        ]);

        if ($seat->getStatus() !== 'available') {
            return $this->jsonResponse([
                'message' => 'Seat is not available',
                'status' => $seat->getStatus(),
            ], 422);
        }

        $updatedSeat = $this->seatRepository->updateWhere(
            attributes: [
                'attendee_id' => $validated['attendee_id'],
                'product_id' => $validated['product_id'] ?? null,
                'status' => 'sold',
            ],
            where: [
                'id' => $seatId,
                'chart_id' => $seatingChartId,
                'status' => 'available',
            ],
        );

        return $this->jsonResponse(['message' => 'Seat assigned successfully']);
    }
}
