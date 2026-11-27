<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\CrmCampaignDomainObject;

class DuplicateCampaignResultDTO extends BaseDataObject
{
    /**
     * @param  string[]  $warnings
     */
    public function __construct(
        public readonly CrmCampaignDomainObject $campaign,
        public readonly array $warnings,
    ) {}
}
