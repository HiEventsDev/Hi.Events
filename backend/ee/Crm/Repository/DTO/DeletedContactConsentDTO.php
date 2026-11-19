<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;

class DeletedContactConsentDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?CrmEmailMarketingStatus $suppression,
        public readonly bool $sms_opted_out,
    ) {}
}
