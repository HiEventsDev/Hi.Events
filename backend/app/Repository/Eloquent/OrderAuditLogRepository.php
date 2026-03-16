<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrderAuditLogDomainObject;
use HiEvents\Models\OrderAuditLog;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;

/**
 * @extends BaseRepository<OrderAuditLogDomainObject>
 */
class OrderAuditLogRepository extends BaseRepository implements OrderAuditLogRepositoryInterface
{
    protected function getModel(): string
    {
        return OrderAuditLog::class;
    }

    public function getDomainObject(): string
    {
        return OrderAuditLogDomainObject::class;
    }
}
