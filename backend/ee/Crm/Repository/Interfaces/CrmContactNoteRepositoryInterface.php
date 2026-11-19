<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmContactNoteDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmContactNoteDomainObject>
 */
interface CrmContactNoteRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, CrmContactNoteDomainObject>
     */
    public function findForContact(int $contactId): Collection;
}
