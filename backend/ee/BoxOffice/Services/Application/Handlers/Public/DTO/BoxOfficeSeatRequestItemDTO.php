<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeSeatRequestItemDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $product_id,
        public readonly int $quantity,
    ) {}
}
