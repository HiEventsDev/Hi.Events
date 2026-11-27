<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SuppressionSummaryDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $unsubscribed,
        public readonly int $bounced,
        public readonly int $complained,
        public readonly int $deleted,
    ) {}
}
