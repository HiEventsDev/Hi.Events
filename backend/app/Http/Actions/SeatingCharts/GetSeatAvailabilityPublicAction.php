<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\SeatingCharts;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\SeatingChartRepositoryInterface;
use HiEvents\Repository\Interfaces\SeatRepositoryInterface;
use Illuminate\Http\JsonResponse;

class GetSeatAvailabilityPublicAction extends BaseAction
{
    public function __construct(
        private readonly SeatingChartRepositoryInterface $seatingChartRepository,
        private readonly SeatRepositoryInterface $seatRepository,
    )
    {
    }

    public function __invoke(int $eventId, int $seatingChartId): JsonResponse
    {
        $chart = $this->seatingChartRepository->findFirstWhere([
            'id' => $seatingChartId,
            'event_id' => $eventId,
            'is_active' => true,
        ]);

        $seats = $this->seatRepository->findByChartId($seatingChartId);

        $seatsBySection = $seats->groupBy(fn($seat) => $seat->getSectionId());

        $availability = $seatsBySection->map(function ($sectionSeats) {
            return $sectionSeats->map(fn($seat) => [
                'id' => $seat->getId(),
                'row_label' => $seat->getRowLabel(),
                'seat_number' => $seat->getSeatNumber(),
                'label' => $seat->getLabel(),
                'status' => $seat->getStatus(),
                'category' => $seat->getCategory(),
                'price_override' => $seat->getPriceOverride(),
                'is_accessible' => $seat->getIsAccessible(),
                'position' => $seat->getPosition(),
            ])->values()->toArray();
        });

        return $this->jsonResponse([
            'chart_id' => $chart->getId(),
            'name' => $chart->getName(),
            'layout' => $chart->getLayout(),
            'total_seats' => $chart->getTotalSeats(),
            'sections' => $availability,
        ]);
    }
}
