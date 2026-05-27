<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EventLocationDomainObject;

/**
 * @extends RepositoryInterface<EventLocationDomainObject>
 */
interface EventLocationRepositoryInterface extends RepositoryInterface
{
    public function isReferenced(int $eventLocationId): bool;
}
