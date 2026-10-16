<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventLineupEntryDomainObject
 */
class LineupEntryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'profile_id' => $this->getProfileId(),
            /** @var 'HEADLINER'|'FEATURED'|'STANDARD' */
            'billing_tier' => $this->getBillingTier(),
            'role_label' => $this->getRoleLabel(),
            'is_hidden' => $this->getIsHidden(),
            'sort_order' => $this->getSortOrder(),
            'profile' => new ProfileResource($this->getProfile()),
        ];
    }
}
