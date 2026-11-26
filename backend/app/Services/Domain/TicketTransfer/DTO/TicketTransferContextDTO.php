<?php

namespace HiEvents\Services\Domain\TicketTransfer\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use Illuminate\Support\Collection;

class TicketTransferContextDTO extends BaseDataObject
{
    /**
     * @param  Collection<int, TicketTransferDomainObject>  $transfers
     */
    public function __construct(
        public readonly EventDomainObject $event,
        public readonly OrderDomainObject $order,
        public readonly AttendeeDomainObject $attendee,
        public readonly ProductDomainObject $product,
        public readonly EventOccurrenceDomainObject $occurrence,
        public readonly Collection $transfers,
        public readonly bool $is_checked_in,
        public readonly bool $has_open_listing,
    ) {}

    public function pendingTransfer(): ?TicketTransferDomainObject
    {
        return $this->transfers->first(fn (TicketTransferDomainObject $transfer) => $transfer->isAwaitingRecipient());
    }
}
