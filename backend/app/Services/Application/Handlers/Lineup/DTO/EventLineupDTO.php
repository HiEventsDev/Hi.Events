<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class EventLineupDTO extends BaseDataObject
{
    public function __construct(
        public readonly Collection $entries,
        public readonly Collection $spaces,
        public readonly Collection $schedule_items,
        public readonly ?Collection $venues = null,
        public readonly ?array $schedule_days_by_entry_id = null,
    ) {}
}
