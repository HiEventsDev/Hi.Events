<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeOrderItemDTO extends BaseDataObject
{
    public function __construct(
        public int $product_id,
        public int $product_price_id,
        public int $quantity,
        public ?float $override_price = null,
        /** @var string[] */
        public array $seat_uids = [],
    ) {}
}
