<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\EmailTemplateEngine;
use HiEvents\DomainObjects\Enums\EmailTemplateType;

class UpsertEmailTemplateDTO extends BaseDataObject
{
    public function __construct(
        public readonly int                 $account_id,
        public readonly EmailTemplateType   $template_type,
        public readonly string              $subject,
        public readonly string              $body,
        public readonly ?int                $organizer_id = null,
        public readonly ?int                $event_id = null,
        public readonly ?int                $id = null,
        public readonly ?array              $cta = null,
        public readonly EmailTemplateEngine $engine = EmailTemplateEngine::LIQUID,
        public readonly bool                $is_active = true,
    )
    {
    }
}
