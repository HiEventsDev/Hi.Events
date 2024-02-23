<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\StripePaymentDomainObject;
use TicketKitten\Models\StripePayment;
use TicketKitten\Repository\Interfaces\StripePaymentsRepositoryInterface;

class StripePaymentsRepository extends BaseRepository implements StripePaymentsRepositoryInterface
{
    protected function getModel(): string
    {
        return StripePayment::class;
    }

    public function getDomainObject(): string
    {
        return StripePaymentDomainObject::class;
    }
}
