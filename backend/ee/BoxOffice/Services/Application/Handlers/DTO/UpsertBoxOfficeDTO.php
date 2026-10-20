<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertBoxOfficeDTO extends BaseDataObject
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $event_id,
        public array $product_ids,
        public ?int $event_occurrence_id = null,
        public ?int $check_in_list_id = null,
        public bool $allow_price_override = false,
        public bool $allow_discounts = true,
        public bool $collect_order_questions = false,
        public ?string $activates_at = null,
        public ?string $expires_at = null,
        public ?int $id = null,
    ) {}
}
