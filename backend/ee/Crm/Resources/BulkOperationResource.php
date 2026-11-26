<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmBulkOperationDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmBulkOperationDomainObject
 */
class BulkOperationResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            /** @var 'ADD_TAGS'|'REMOVE_TAGS'|'UNSUBSCRIBE'|'DELETE' */
            'action' => $this->getAction(),
            'tag_ids' => $this->getTagIds() ?? [],
            /** @var 'RUNNING'|'COMPLETED'|'FAILED'|'UNDONE' */
            'status' => $this->getStatus(),
            'total' => $this->getTotal(),
            'processed' => $this->getProcessed(),
            'changed' => $this->getChanged(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
