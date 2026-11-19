<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SuppressionImportPreviewDTO extends BaseDataObject
{
    /**
     * @param  array<string, int>  $reasons
     * @param  array<int, array{email: string, reason: string}>  $sample
     */
    public function __construct(
        public readonly int $total_rows,
        public readonly int $valid_rows,
        public readonly int $invalid_rows,
        public readonly int $duplicate_rows,
        public readonly int $new_contacts,
        public readonly int $existing_contacts,
        public readonly int $subscribed_contacts,
        public readonly int $deleted_contacts,
        public readonly array $reasons,
        public readonly array $sample,
    ) {}
}
