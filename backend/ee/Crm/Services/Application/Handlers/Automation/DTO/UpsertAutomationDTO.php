<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationConfigDTO;

class UpsertAutomationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly CrmAutomationTrigger $trigger,
        public readonly string $name,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?int $segment_id,
        public readonly ?string $preset,
        public readonly AutomationConfigDTO $config,
        public readonly bool $can_view_financials,
        public readonly ?int $automation_id = null,
        public readonly ?string $preheader = null,
    ) {}
}
