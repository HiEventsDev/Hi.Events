<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;

class PublicEventSeatMapDTO extends BaseDataObject
{
    public function __construct(
        public readonly EventDomainObject $event,
        public readonly EventSeatMapDomainObject $eventSeatMap,
    ) {}
}
