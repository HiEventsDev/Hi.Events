<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GetBadgesDTO extends BaseDataObject
{
    /**
     * @param  int[]|null  $attendee_ids
     * @param  int[]|null  $product_ids
     */
    public function __construct(
        public int $event_id,
        public ?array $attendee_ids,
        public ?int $order_id,
        public ?array $product_ids,
        public ?int $event_occurrence_id,
        public bool $only_unprinted,
        public string $sort_by,
        public int $page,
        public int $per_page,
    ) {}
}
