<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\PosSessionDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<PosSessionDomainObject>
 */
interface PosSessionRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId): Collection;

    public function findActiveByEventId(int $eventId): Collection;
}
