<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetUpcomingEventsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $perPage = 20,
    )
    {
    }
}
