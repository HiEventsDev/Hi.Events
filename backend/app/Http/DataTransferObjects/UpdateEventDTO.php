<?php

namespace TicketKitten\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use TicketKitten\DomainObjects\Status\EventStatus;
use TicketKitten\DataTransferObjects\Attributes\CollectionOf;
use TicketKitten\DataTransferObjects\BaseDTO;

class UpdateEventDTO extends BaseDTO
{
    public function __construct(
        public readonly string      $title,
        public readonly int         $account_id,
        public readonly int         $id,
        public readonly ?string     $start_date = null,
        public readonly ?string     $end_date = null,
        public readonly ?string     $description = null,
        #[CollectionOf(AttributesDTO::class)]
        public readonly ?Collection $attributes = null,
        public readonly ?string     $timezone = null,
        public readonly ?string     $currency = null,
        public readonly ?string     $location = null,
        public readonly ?AddressDTO $location_details = null,
        public readonly ?string     $status = EventStatus::DRAFT->name,
    )
    {
    }
}
