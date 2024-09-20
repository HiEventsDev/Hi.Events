<?php

namespace HiEvents\Services\Domain\Product\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use Illuminate\Support\Collection;

class AvailableProductQuantitiesResponseDTO extends BaseDTO
{
    public function __construct(
        /** @var Collection<AvailableProductQuantitiesDTO> */
        public Collection  $productQuantities,
        /** @var Collection<CapacityAssignmentDomainObject> */
        public ?Collection $capacities = null,
    )
    {
    }
}
