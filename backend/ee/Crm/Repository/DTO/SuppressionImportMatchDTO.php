<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SuppressionImportMatchDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $emails,
        public readonly int $contacts,
        public readonly int $subscribed,
    ) {}
}
