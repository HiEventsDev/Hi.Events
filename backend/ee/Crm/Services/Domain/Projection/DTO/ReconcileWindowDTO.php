<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Projection\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ReconcileWindowDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $since,
        public readonly string $until,
        public readonly int $after_order_id = 0,
        public readonly int $after_entry_id = 0,
        public readonly bool $orders_done = false,
    ) {}
}
