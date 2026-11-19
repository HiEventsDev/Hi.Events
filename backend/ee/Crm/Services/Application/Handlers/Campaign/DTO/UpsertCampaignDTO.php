<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;

class UpsertCampaignDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly int $user_id,
        public readonly string $name,
        public readonly ?string $subject,
        public readonly ?string $body,
        public readonly SegmentReferenceDTO $segment,
        public readonly ?int $event_id,
        public readonly bool $can_view_financials,
        public readonly ?int $campaign_id = null,
        public readonly ?string $preheader = null,
        public readonly array $early_access_product_ids = [],
        public readonly ?int $template_id = null,
        public readonly CrmMarketingChannel $channel = CrmMarketingChannel::EMAIL,
    ) {}
}
