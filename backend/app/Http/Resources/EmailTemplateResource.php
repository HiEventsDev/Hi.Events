<?php

namespace HiEvents\Http\Resources;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EmailTemplateDomainObject
 */
class EmailTemplateResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'organizer_id' => $this->getOrganizerId(),
            'event_id' => $this->getEventId(),
            'template_type' => $this->getTemplateType(),
            'subject' => $this->getSubject(),
            'body' => $this->getBody(),
            'cta' => $this->getCta(),
            'engine' => $this->getEngine(),
            'is_active' => $this->getIsActive(),
        ];
    }
}
