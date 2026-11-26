<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CampaignSendCheckDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $mailable_count,
        public readonly bool $requires_review,
        public readonly ?string $review_reason,
        public readonly ?string $blocked_reason,
        public readonly ?string $blocked_reason_code = null,
        public readonly ?array $blocked_reason_params = null,
        public readonly array $warnings = [],
        public readonly array $early_access_tickets = [],
    ) {}
}
