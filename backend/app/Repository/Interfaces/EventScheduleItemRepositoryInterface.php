<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EventScheduleItemDomainObject;

/**
 * @extends RepositoryInterface<EventScheduleItemDomainObject>
 */
interface EventScheduleItemRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  int[]  $eventIds
     */
    public function deleteUntitledWithoutPerformers(array $eventIds): int;
}
