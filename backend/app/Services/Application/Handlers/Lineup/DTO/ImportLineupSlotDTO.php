<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ImportLineupSlotDTO extends BaseDataObject
{
    /**
     * @param  string[]  $people
     */
    public function __construct(
        public readonly string $starts_at,
        public readonly string $ends_at,
        public readonly ?string $stage = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly array $people = [],
    ) {}
}
