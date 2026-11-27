<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Import\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ImportSkippedReasonsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $invalid_email,
        public readonly int $duplicate_in_file,
        public readonly int $previously_deleted,
        public readonly int $already_suppressed = 0,
        public readonly int $bounced_secondary_address = 0,
    ) {}
}
