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
    public const SCHEDULE_DAYS_BY_ENTRY_ID = 'schedule_days_by_entry_id';

    public function toArray(Request $request): array
    {
        $scheduleDays = $this->getAdditionalDataByKey(self::SCHEDULE_DAYS_BY_ENTRY_ID);

        return [
            'id' => $this->getId(),
            /** @var 'HEADLINER'|'FEATURED'|'STANDARD' */
            'billing_tier' => $this->getBillingTier(),
            'role_label' => $this->getRoleLabel(),
            'profile' => new ProfileResourcePublic($this->getProfile()),
            $this->mergeWhen($scheduleDays !== null, fn () => [
                /** @var list<string> */
                'schedule_days' => $scheduleDays[$this->getId()] ?? [],
            ]),
        ];
    }
}
