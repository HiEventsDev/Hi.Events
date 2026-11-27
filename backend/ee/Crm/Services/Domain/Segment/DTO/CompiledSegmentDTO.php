<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CompiledSegmentDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings,
    ) {}
}
