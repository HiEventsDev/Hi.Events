<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmSegmentDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentUsageDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmSegmentDomainObject
 */
class SegmentResource extends BaseResource
{
    public function toArray($request): array
    {
        $definition = $this->getDefinition();
        $hasRestrictedRules = ! $this->isPermitted(Permission::VIEW_FINANCIALS) && SegmentDefinitionNormalizer::hasFinancialRules($definition);

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'definition' => $hasRestrictedRules ? SegmentDefinitionNormalizer::withoutFinancialRules($definition) : $definition,
            'has_restricted_rules' => $hasRestrictedRules,
            'usages' => array_map(static fn (SegmentUsageDTO $usage) => [
                /** @var 'CAMPAIGN'|'AUTOMATION' */
                'type' => $usage->type,
                'id' => $usage->id,
                'name' => $usage->name,
                /** @var 'EVENT_PUBLISHED'|'AFTER_EVENT'|'BEFORE_EVENT'|'ABANDONED_CHECKOUT'|'SEGMENT_ENTERED'|'WIN_BACK'|'WELCOME'|null */
                'trigger' => $usage->trigger,
                /** @var 'DRAFT'|'REJECTED'|'SCHEDULED'|'ENABLED'|'DISABLED' */
                'status' => $usage->status,
                'blocks_deletion' => $usage->blocks_deletion,
            ], $this->getUsages()),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
