<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;

class PreviewCampaignDraftDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly ?string $subject,
        public readonly ?string $preheader,
        public readonly ?string $body,
        public readonly ?int $event_id,
        public readonly array $early_access_product_ids,
        public readonly ?int $contact_id,
        public readonly CampaignRecipientDTO $viewer,
    ) {}
}
