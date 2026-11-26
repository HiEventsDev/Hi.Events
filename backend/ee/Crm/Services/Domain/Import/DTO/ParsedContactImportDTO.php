<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Import\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;

class ParsedContactImportDTO extends BaseDataObject
{
    /**
     * @param  array<string, array{email: string, first_name: string|null, last_name: string|null, reason: CrmEmailMarketingStatus}>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $total_rows,
        public readonly int $invalid_rows,
    ) {}
}
