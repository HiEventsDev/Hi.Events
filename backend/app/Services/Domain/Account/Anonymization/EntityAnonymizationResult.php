<?php

namespace HiEvents\Services\Domain\Account\Anonymization;

use HiEvents\DataTransferObjects\BaseDataObject;

class EntityAnonymizationResult extends BaseDataObject
{
    public function __construct(
        public readonly string $entity,
        public readonly string $action,
        public readonly int $rowCount,
        public readonly array $columns = [],
    ) {}
}
