<?php

namespace HiEvents\Http\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetSitemapEventsDTO extends BaseDataObject
{
    public function __construct(
        public int $page,
    )
    {
    }
}
