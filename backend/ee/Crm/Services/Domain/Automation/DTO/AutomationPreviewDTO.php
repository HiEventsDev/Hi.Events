<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AutomationPreviewDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $mailable_count,
        public readonly AutomationFirstSendDTO $first_send,
        public readonly string $subject,
        public readonly string $html,
    ) {}
}
