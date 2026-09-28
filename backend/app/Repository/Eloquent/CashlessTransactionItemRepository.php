<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\Models\CashlessTransactionItem;
use HiEvents\Repository\Interfaces\CashlessTransactionItemRepositoryInterface;

/**
 * @extends BaseRepository<CashlessTransactionItemDomainObject>
 */
class CashlessTransactionItemRepository extends BaseRepository implements CashlessTransactionItemRepositoryInterface
{
    protected function getModel(): string
    {
        return CashlessTransactionItem::class;
    }

    public function getDomainObject(): string
    {
        return CashlessTransactionItemDomainObject::class;
    }
}
