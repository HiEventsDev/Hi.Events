<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmContactNoteDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmContactNoteDomainObject
 */
class ContactNoteResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'note' => $this->getNote(),
            'author_name' => $this->getAuthorName(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
