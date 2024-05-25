<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Models\EventSetting;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;

class EventSettingsRepository extends BaseRepository implements EventSettingsRepositoryInterface
{
    protected function getModel(): string
    {
        return EventSetting::class;
    }

    public function getDomainObject(): string
    {
        return EventSettingDomainObject::class;
    }
}
