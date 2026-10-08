<?php

namespace HiEvents\Services\Domain\Product\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;

class ProductFilterEventContextDTO extends BaseDataObject
{
    public function __construct(
        public readonly EventDomainObject $event,
        public readonly ?OrganizerConfigurationDomainObject $organizerConfiguration,
        public readonly AvailableProductQuantitiesResponseDTO $productQuantities,
    ) {}
}
