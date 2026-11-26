<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class SegmentOptionsDTO extends BaseDataObject
{
    /**
     * @param  string[]  $currencies
     */
    public function __construct(
        public readonly Collection $events,
        public readonly Collection $products,
        public readonly Collection $locations,
        public readonly array $currencies,
    ) {}
}
