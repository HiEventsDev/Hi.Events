<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DataTransferObjects\BaseDataObject;

class SmsPreviewDTO extends BaseDataObject
{
    /**
     * @param  SmsPreviewVariantDTO[]  $variants
     */
    public function __construct(
        public readonly array $variants,
        public readonly bool $uses_placeholders,
        public readonly ?SmsCampaignEstimateDTO $estimate = null,
    ) {}
}
