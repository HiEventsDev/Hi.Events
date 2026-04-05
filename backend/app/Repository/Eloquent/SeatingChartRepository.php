<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SeatingChartDomainObject;
use HiEvents\Models\SeatingChart;
use HiEvents\Repository\Interfaces\SeatingChartRepositoryInterface;
use Illuminate\Support\Collection;

class SeatingChartRepository extends BaseRepository implements SeatingChartRepositoryInterface
{
    protected function getModel(): string
    {
        return SeatingChart::class;
    }

    public function getDomainObject(): string
    {
        return SeatingChartDomainObject::class;
    }

    public function findByEventId(int $eventId): Collection
    {
        $results = $this->model
            ->where('event_id', $eventId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $results->map(fn($model) => SeatingChartDomainObject::hydrateFromModel($model));
    }
}
