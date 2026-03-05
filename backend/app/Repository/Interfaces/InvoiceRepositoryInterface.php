<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\InvoiceDomainObject;

/**
 * @extends RepositoryInterface<InvoiceDomainObject>
 */
interface InvoiceRepositoryInterface extends RepositoryInterface
{
    public function findLatestInvoiceForEvent(int $eventId): ?InvoiceDomainObject;

    public function findLatestInvoiceForOrder(int $orderId): ?InvoiceDomainObject;
}
