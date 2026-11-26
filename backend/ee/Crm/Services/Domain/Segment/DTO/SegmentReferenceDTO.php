<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SegmentReferenceDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?int $segment_id = null,
        public readonly ?string $preset = null,
        public readonly ?array $definition = null,
    ) {}
}
