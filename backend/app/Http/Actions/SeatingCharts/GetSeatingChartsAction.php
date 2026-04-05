<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\SeatingCharts;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\SeatingChartRepositoryInterface;
use Illuminate\Http\JsonResponse;

class GetSeatingChartsAction extends BaseAction
{
    public function __construct(
        private readonly SeatingChartRepositoryInterface $seatingChartRepository,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $charts = $this->seatingChartRepository->findByEventId($eventId);

        return $this->jsonResponse($charts->map(fn($chart) => $chart->toArray()));
    }
}
