<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class PresetSegmentDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $key,
        public readonly array $definition,
    ) {}
}
