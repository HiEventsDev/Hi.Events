<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BandProductLinkDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $product_id,
        public readonly int $price_adjustment = 0,
    ) {}
}
