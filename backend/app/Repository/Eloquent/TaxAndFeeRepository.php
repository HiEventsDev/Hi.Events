<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Models\TaxAndFee;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;

/**
 * @extends BaseRepository<TaxAndFeesDomainObject>
 */
class TaxAndFeeRepository extends BaseRepository implements TaxAndFeeRepositoryInterface
{
    public function getDomainObject(): string
    {
        return TaxAndFeesDomainObject::class;
    }

    protected function getModel(): string
    {
        return TaxAndFee::class;
    }
}
