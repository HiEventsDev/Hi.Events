<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\EventSettingDomainObject;
use TicketKitten\Models\EventSetting;
use TicketKitten\Repository\Interfaces\EventSettingsRepositoryInterface;

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
