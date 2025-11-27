<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;

class GetEmailTemplatesDTO extends BaseDataObject
{
    public function __construct(
        public readonly int                $account_id,
        public readonly ?int               $organizer_id = null,
        public readonly ?int               $event_id = null,
        public readonly ?EmailTemplateType $template_type = null,
        public readonly bool               $include_inactive = false,
    )
    {
    }
}
