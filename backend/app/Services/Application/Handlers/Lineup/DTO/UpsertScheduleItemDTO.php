<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertScheduleItemDTO extends BaseDataObject
{
    /**
     * @param  int[]  $lineup_entry_ids
     */
    public function __construct(
        public readonly int $event_id,
        public readonly string $starts_at,
        public readonly string $ends_at,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?int $event_space_id = null,
        public readonly array $lineup_entry_ids = [],
        public readonly ?int $schedule_item_id = null,
    ) {}
}
