<?php

namespace HiEvents\Repository\DTO\ProductPurchase;

use HiEvents\DataTransferObjects\BaseDataObject;

class ProductPurchaseFilterDTO extends BaseDataObject
{
    /**
     * @param  string[]  $statuses
     * @param  string[]  $refundStatuses
     */
    public function __construct(
        public int $eventId,
        public ?int $productId = null,
        public ?int $eventOccurrenceId = null,
        public array $statuses = [],
        public array $refundStatuses = [],
        public ?string $query = null,
    ) {}
}
