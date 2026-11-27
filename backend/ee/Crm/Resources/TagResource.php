<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmTagDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmTagDomainObject
 */
class TagResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'color' => $this->getColor(),
            'contacts_count' => $this->getContactsCount(),
        ];
    }
}
