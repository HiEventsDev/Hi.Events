<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\Models\OrganizerVatSetting;
use HiEvents\Repository\Interfaces\OrganizerVatSettingRepositoryInterface;

/**
 * @extends BaseRepository<OrganizerVatSettingDomainObject>
 */
class OrganizerVatSettingRepository extends BaseRepository implements OrganizerVatSettingRepositoryInterface
{
    protected function getModel(): string
    {
        return OrganizerVatSetting::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerVatSettingDomainObject::class;
    }

    public function findByOrganizerId(int $organizerId): ?OrganizerVatSettingDomainObject
    {
        return $this->findFirstWhere(['organizer_id' => $organizerId]);
    }
}
