<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Models\TicketPrice;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;

class TicketPriceRepository extends BaseRepository implements TicketPriceRepositoryInterface
{
    protected function getModel(): string
    {
        return TicketPrice::class;
    }

    public function getDomainObject(): string
    {
        return TicketPriceDomainObject::class;
    }
}
