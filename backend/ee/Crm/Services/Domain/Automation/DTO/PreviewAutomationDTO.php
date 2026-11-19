<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;

class PreviewAutomationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly CrmAutomationTrigger $trigger,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?int $segment_id,
        public readonly ?string $preset,
        public readonly AutomationConfigDTO $config,
        public readonly bool $can_view_financials,
        public readonly CampaignRecipientDTO $viewer_name,
        public readonly ?string $preheader = null,
        public readonly ?int $automation_id = null,
    ) {}
}
