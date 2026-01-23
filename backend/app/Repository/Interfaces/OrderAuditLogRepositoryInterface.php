<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderAuditLogDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<OrderAuditLogDomainObject>
 */
interface OrderAuditLogRepositoryInterface extends RepositoryInterface
{

}
