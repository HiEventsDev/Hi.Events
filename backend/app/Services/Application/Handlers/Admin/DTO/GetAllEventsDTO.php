<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;

class GetAllEventsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int     $perPage = 20,
        public readonly ?string $search = null,
        public readonly ?string $sortBy = EventDomainObjectAbstract::START_DATE,
        public readonly ?string $sortDirection = 'desc',
    )
    {
    }
}
