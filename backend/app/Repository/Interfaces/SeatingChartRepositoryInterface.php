<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\SeatingChartDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<SeatingChartDomainObject>
 */
interface SeatingChartRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId): Collection;
}
