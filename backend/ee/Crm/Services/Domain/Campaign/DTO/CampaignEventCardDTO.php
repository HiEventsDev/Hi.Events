<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CampaignEventCardDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $date_line,
        public readonly ?string $location_line,
        public readonly ?string $image_url,
        public readonly string $url,
        public readonly string $button_label,
    ) {}
}
