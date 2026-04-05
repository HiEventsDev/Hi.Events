<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\SeatDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<SeatDomainObject>
 */
interface SeatRepositoryInterface extends RepositoryInterface
{
    public function findByChartId(int $chartId): Collection;

    public function findAvailableByChartId(int $chartId): Collection;

    public function findBySectionId(int $sectionId): Collection;
}
