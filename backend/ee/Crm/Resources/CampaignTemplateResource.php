<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmCampaignTemplateDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmCampaignTemplateDomainObject
 */
class CampaignTemplateResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'subject' => $this->getSubject(),
            'preheader' => $this->getPreheader(),
            'body' => $this->getBody(),
            'last_used_at' => $this->getLastUsedAt(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
