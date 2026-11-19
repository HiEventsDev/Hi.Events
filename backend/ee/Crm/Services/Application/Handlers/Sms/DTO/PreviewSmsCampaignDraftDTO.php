<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;

class PreviewSmsCampaignDraftDTO extends BaseDataObject
{
    /**
     * @param  int[]  $early_access_product_ids
     */
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly ?string $body,
        public readonly ?int $event_id,
        public readonly array $early_access_product_ids,
        public readonly ?int $campaign_id,
        public readonly bool $can_view_financials,
        public readonly CampaignRecipientDTO $viewer,
    ) {}
}
