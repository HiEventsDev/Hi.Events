<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CampaignStarterDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly string $subject,
        public readonly string $preheader,
        public readonly string $body,
    ) {}
}
