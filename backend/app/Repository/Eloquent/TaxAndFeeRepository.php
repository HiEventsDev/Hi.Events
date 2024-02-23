<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\Models\TaxAndFee;
use TicketKitten\Repository\Interfaces\TaxAndFeeRepositoryInterface;

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
