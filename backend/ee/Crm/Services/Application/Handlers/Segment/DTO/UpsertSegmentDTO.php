<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertSegmentDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $definition,
        public readonly bool $can_view_financials,
        public readonly ?int $segment_id = null,
    ) {}
}
