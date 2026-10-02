<?php

namespace HiEvents\Enterprise\BoxOffice\Repository\Interfaces;

use HiEvents\DomainObjects\StripeTerminalReaderDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;

/**
 * @extends RepositoryInterface<StripeTerminalReaderDomainObject>
 */
interface StripeTerminalReaderRepositoryInterface extends RepositoryInterface
{
    public function existsForLiveOrganizer(?int $accountId): bool;
}
