<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\TicketTransferDomainObject;

/**
 * @extends RepositoryInterface<TicketTransferDomainObject>
 */
interface TicketTransferRepositoryInterface extends RepositoryInterface
{
    public function findByTokenHashLocked(string $tokenHash): ?TicketTransferDomainObject;
}
