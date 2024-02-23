<?php

namespace TicketKitten\Http\DataTransferObjects;

use Illuminate\Http\UploadedFile;
use TicketKitten\DomainObjects\Enums\EventImageType;
use TicketKitten\DataTransferObjects\BaseDTO;

class CreateEventImageDTO extends BaseDTO
{
    public function __construct(
        public readonly int            $event_id,
        public readonly UploadedFile   $image,
        public readonly EventImageType $type,
    )
    {
    }
}
