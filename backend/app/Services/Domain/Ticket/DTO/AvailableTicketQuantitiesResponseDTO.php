<?php

namespace HiEvents\Services\Domain\Ticket\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use Illuminate\Support\Collection;

class AvailableTicketQuantitiesResponseDTO extends BaseDTO
{
    public function __construct(
        /** @var Collection<AvailableTicketQuantitiesDTO> */
        public Collection  $ticketQuantities,
        /** @var Collection<CapacityAssignmentDomainObject> */
        public ?Collection $capacities = null,
    )
    {
    }
}
