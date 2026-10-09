<?php

namespace HiEvents\Services\Application\Handlers\Product\Purchases\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Http\DTO\QueryParamsDTO;

class GetProductPurchasesDTO extends BaseDataObject
{
    public function __construct(
        public int $eventId,
        public int $productId,
        public QueryParamsDTO $queryParams,
    ) {}
}
