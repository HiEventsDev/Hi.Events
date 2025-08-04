<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Models\OrganizerSetting;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;

class OrganizerSettingsRepository extends BaseRepository implements OrganizerSettingsRepositoryInterface
{
    protected function getModel(): string
    {
        return OrganizerSetting::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerSettingDomainObject::class;
    }
}
