<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SeatDomainObject;
use HiEvents\Models\Seat;
use HiEvents\Repository\Interfaces\SeatRepositoryInterface;
use Illuminate\Support\Collection;

class SeatRepository extends BaseRepository implements SeatRepositoryInterface
{
    protected function getModel(): string
    {
        return Seat::class;
    }

    public function getDomainObject(): string
    {
        return SeatDomainObject::class;
    }

    public function findByChartId(int $chartId): Collection
    {
        $results = $this->model
            ->where('chart_id', $chartId)
            ->orderBy('section_id')
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        return $results->map(fn($model) => SeatDomainObject::hydrateFromModel($model));
    }

    public function findAvailableByChartId(int $chartId): Collection
    {
        $results = $this->model
            ->where('chart_id', $chartId)
            ->where('status', 'available')
            ->orderBy('section_id')
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        return $results->map(fn($model) => SeatDomainObject::hydrateFromModel($model));
    }

    public function findBySectionId(int $sectionId): Collection
    {
        $results = $this->model
            ->where('section_id', $sectionId)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();

        return $results->map(fn($model) => SeatDomainObject::hydrateFromModel($model));
    }
}
