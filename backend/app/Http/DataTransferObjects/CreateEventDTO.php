<?php

namespace TicketKitten\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use TicketKitten\DataTransferObjects\Attributes\CollectionOf;
use TicketKitten\DataTransferObjects\BaseDTO;
use TicketKitten\DomainObjects\Status\EventStatus;

class CreateEventDTO extends BaseDTO
{
    public function __construct(
        public readonly string      $title,
        public readonly int         $organizer_id,
        public readonly int         $account_id,
        public readonly int         $user_id,
        public readonly ?int        $id = null,
        public readonly ?string     $start_date = null,
        public readonly ?string     $end_date = null,
        public readonly ?string     $description = null,
        #[CollectionOf(AttributesDTO::class)]
        public readonly ?Collection $attributes = null,
        public readonly ?string     $timezone = null,
        public readonly ?string     $currency = null,
        public readonly ?AddressDTO $location_details = null,
        public readonly ?string     $status = EventStatus::DRAFT->name,
    )
    {
    }
}
