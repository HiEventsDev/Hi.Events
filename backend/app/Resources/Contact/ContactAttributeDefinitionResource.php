<?php

namespace HiEvents\Resources\Contact;

use HiEvents\DomainObjects\ContactAttributeDefinitionDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin ContactAttributeDefinitionDomainObject
 */
class ContactAttributeDefinitionResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'type' => $this->getType(),
            'options' => $this->getOptions(),
            'sort_order' => $this->getSortOrder(),
            'is_active' => $this->getIsActive(),
            'linked_question_count' => $this->getLinkedQuestionCount() ?? 0,
            'linked_event_count' => $this->getLinkedEventCount() ?? 0,
        ];
    }
}
