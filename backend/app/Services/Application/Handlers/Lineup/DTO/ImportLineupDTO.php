<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ImportLineupDTO extends BaseDataObject
{
    /**
     * @param  ImportLineupPersonDTO[]  $people
     * @param  ImportLineupSlotDTO[]  $slots
     */
    public function __construct(
        public readonly int $event_id,
        public readonly array $people,
        public readonly array $slots,
    ) {}
}
