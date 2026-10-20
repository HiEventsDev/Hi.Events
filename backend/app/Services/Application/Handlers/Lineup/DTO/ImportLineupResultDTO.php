<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ImportLineupResultDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $people_created,
        public readonly int $people_updated,
        public readonly int $people_added,
        public readonly int $stages_created,
        public readonly int $slots_created,
        public readonly int $slots_skipped,
    ) {}
}
