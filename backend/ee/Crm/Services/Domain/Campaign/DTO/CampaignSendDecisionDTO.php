<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CampaignSendDecisionDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $requires_review,
        public readonly ?string $review_reason,
    ) {}
}
