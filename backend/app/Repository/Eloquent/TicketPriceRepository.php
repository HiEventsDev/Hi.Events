<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Models\TicketPrice;
use TicketKitten\Repository\Interfaces\TicketPriceRepositoryInterface;

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
