<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertEventSpaceDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly string $name,
        public readonly ?int $event_location_id = null,
        public readonly ?int $space_id = null,
    ) {}
}
