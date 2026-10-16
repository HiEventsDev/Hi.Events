<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventLineupEntryDomainObject
 */
class LineupEntryResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            /** @var 'HEADLINER'|'FEATURED'|'STANDARD' */
            'billing_tier' => $this->getBillingTier(),
            'role_label' => $this->getRoleLabel(),
            'profile' => new ProfileResourcePublic($this->getProfile()),
        ];
    }
}
