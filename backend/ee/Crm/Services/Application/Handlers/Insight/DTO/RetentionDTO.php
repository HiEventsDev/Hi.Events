<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class RetentionDTO extends BaseDataObject
{
    /**
     * @param  array<int, array{cohort: string, size: int, months: array<int, array{offset: int, contacts: int, percent: float}>}>  $cohorts
     */
    public function __construct(
        public readonly array $cohorts,
        public readonly int $max_offset,
    ) {}
}
