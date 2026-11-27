<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AutomationTemplateDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $trigger,
        public readonly string $name,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $preset,
        public readonly int $delay_days,
        public readonly int $wait_minutes,
        public readonly int $days_before,
        public readonly string $occurrence_audience,
    ) {}
}
