<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class LeadTimeDTO extends BaseDataObject
{
    /**
     * @param  array<int, array{bucket: int, tickets: int, percent: float}>  $buckets
     */
    public function __construct(
        public readonly array $buckets,
        public readonly int $tickets_count,
    ) {}
}
