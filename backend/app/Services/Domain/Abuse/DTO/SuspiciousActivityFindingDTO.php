<?php

namespace HiEvents\Services\Domain\Abuse\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SuspiciousActivityFindingDTO extends BaseDataObject
{
    /**
     * @param  array<int, string>  $details
     */
    public function __construct(
        public readonly string $type,
        public readonly string $summary,
        public readonly array $details,
        public readonly string $fingerprint,
    ) {}
}
