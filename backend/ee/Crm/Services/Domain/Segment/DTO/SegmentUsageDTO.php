<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SegmentUsageDTO extends BaseDataObject
{
    public const TYPE_CAMPAIGN = 'CAMPAIGN';

    public const TYPE_AUTOMATION = 'AUTOMATION';

    public const STATUS_ENABLED = 'ENABLED';

    public const STATUS_DISABLED = 'DISABLED';

    public function __construct(
        public readonly int $segment_id,
        public readonly string $type,
        public readonly int $id,
        public readonly ?string $name,
        public readonly ?string $trigger,
        public readonly string $status,
        public readonly bool $blocks_deletion,
    ) {}
}
