<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CashlessTopupDomainObject;
use HiEvents\Models\CashlessTopup;
use HiEvents\Repository\Interfaces\CashlessTopupRepositoryInterface;

/**
 * @extends BaseRepository<CashlessTopupDomainObject>
 */
class CashlessTopupRepository extends BaseRepository implements CashlessTopupRepositoryInterface
{
    protected function getModel(): string
    {
        return CashlessTopup::class;
    }

    public function getDomainObject(): string
    {
        return CashlessTopupDomainObject::class;
    }
}
