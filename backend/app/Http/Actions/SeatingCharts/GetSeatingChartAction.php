<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\SeatingCharts;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\SeatingChartRepositoryInterface;
use HiEvents\Repository\Interfaces\SeatRepositoryInterface;
use Illuminate\Http\JsonResponse;

class GetSeatingChartAction extends BaseAction
{
    public function __construct(
        private readonly SeatingChartRepositoryInterface $seatingChartRepository,
        private readonly SeatRepositoryInterface $seatRepository,
    )
    {
    }

    public function __invoke(int $eventId, int $seatingChartId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $chart = $this->seatingChartRepository->findFirstWhere([
            'id' => $seatingChartId,
            'event_id' => $eventId,
        ]);

        $seats = $this->seatRepository->findByChartId($seatingChartId);

        $seatsBySection = $seats->groupBy(fn($seat) => $seat->getSectionId());

        $chartData = $chart->toArray();
        $chartData['seats'] = $seats->map(fn($s) => $s->toArray())->values()->toArray();
        $chartData['seats_by_section'] = $seatsBySection->map(fn($sectionSeats) =>
            $sectionSeats->map(fn($s) => $s->toArray())->values()->toArray()
        )->toArray();

        return $this->jsonResponse($chartData);
    }
}
