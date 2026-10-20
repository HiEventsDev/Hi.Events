<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class WidgetStatsDTO extends BaseDataObject
{
    /**
     * @param  array<string, float>  $revenue
     */
    public function __construct(
        public int $orders = 0,
        public array $revenue = [],
        public int $views = 0,
    ) {}
}
