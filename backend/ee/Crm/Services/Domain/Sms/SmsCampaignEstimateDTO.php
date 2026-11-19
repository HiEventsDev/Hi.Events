<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DataTransferObjects\BaseDataObject;

class SmsCampaignEstimateDTO extends BaseDataObject
{
    /**
     * @param  SmsCountryEstimateDTO[]  $countries
     */
    public function __construct(
        public readonly array $countries,
        public readonly int $recipients,
        public readonly int $message_parts,
    ) {}
}
