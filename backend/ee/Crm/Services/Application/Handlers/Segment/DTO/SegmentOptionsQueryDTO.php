<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SegmentOptionsQueryDTO extends BaseDataObject
{
    /**
     * @param  int[]  $event_ids
     * @param  int[]  $product_ids
     */
    public function __construct(
        public readonly ?string $event_query,
        public readonly array $event_ids,
        public readonly bool $with_products,
        public readonly ?string $product_query,
        public readonly array $product_ids,
    ) {}
}
