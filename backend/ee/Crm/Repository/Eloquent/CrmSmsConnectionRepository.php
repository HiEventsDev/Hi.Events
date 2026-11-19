<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSmsConnectionRepositoryInterface;
use HiEvents\Models\CrmSmsConnection;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<CrmSmsConnectionDomainObject>
 */
class CrmSmsConnectionRepository extends BaseRepository implements CrmSmsConnectionRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmSmsConnection::class;
    }

    public function getDomainObject(): string
    {
        return CrmSmsConnectionDomainObject::class;
    }
}
