<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\TicketLookupTokenDomainObject;
use HiEvents\Models\TicketLookupToken;
use HiEvents\Repository\Interfaces\TicketLookupTokenRepositoryInterface;

class TicketLookupTokenRepository extends BaseRepository implements TicketLookupTokenRepositoryInterface
{
    protected function getModel(): string
    {
        return TicketLookupToken::class;
    }

    public function getDomainObject(): string
    {
        return TicketLookupTokenDomainObject::class;
    }
}
