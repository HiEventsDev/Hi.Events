<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmContactActivityDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<CrmContactActivityDomainObject>
 */
interface CrmContactActivityRepositoryInterface extends RepositoryInterface
{
    public function findForContact(int $contactId, int $page, int $perPage): LengthAwarePaginator;
}
