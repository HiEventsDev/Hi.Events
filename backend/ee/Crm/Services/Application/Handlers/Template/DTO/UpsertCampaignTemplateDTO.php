<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Template\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertCampaignTemplateDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly string $name,
        public readonly ?string $subject,
        public readonly ?string $preheader,
        public readonly string $body,
        public readonly ?int $template_id = null,
    ) {}
}
