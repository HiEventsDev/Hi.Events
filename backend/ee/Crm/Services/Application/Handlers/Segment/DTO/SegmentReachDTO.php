<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SegmentReachDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $contacts_count,
        public readonly int $mailable_count,
        public readonly int $textable_count,
        public readonly int $textable_in_enabled_countries_count,
    ) {}
}
