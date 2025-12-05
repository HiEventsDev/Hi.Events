<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;

class GetAllOrdersDTO extends BaseDataObject
{
    public function __construct(
        public readonly int     $perPage = 20,
        public readonly ?string $search = null,
        public readonly ?string $sortBy = OrderDomainObjectAbstract::CREATED_AT,
        public readonly ?string $sortDirection = 'desc',
    )
    {
    }
}
