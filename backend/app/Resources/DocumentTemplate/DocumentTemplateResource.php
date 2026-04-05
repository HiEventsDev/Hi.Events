<?php

namespace HiEvents\Resources\DocumentTemplate;

use HiEvents\DomainObjects\DocumentTemplateDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentTemplateDomainObject
 */
class DocumentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'event_id' => $this->getEventId(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'content' => $this->getContent(),
            'settings' => $this->getSettings(),
            'is_default' => $this->getIsDefault(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
