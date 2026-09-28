<?php

namespace HiEvents\Services\Application\Handlers\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertCashlessSalesPointDTO extends BaseDataObject
{
    public function __construct(
        public int $event_id,
        public string $name,
        public array $product_ids,
        public bool $allow_staff_topups = true,
        public ?string $description = null,
        public ?string $access_pin = null,
        public ?string $activates_at = null,
        public ?string $expires_at = null,
        public ?int $id = null,
    ) {}
}
