<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\AnnouncementUserDomainObjectAbstract;

class AnnouncementUser extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            AnnouncementUserDomainObjectAbstract::FIRST_SEEN_AT => 'datetime',
            AnnouncementUserDomainObjectAbstract::DISMISSED_AT => 'datetime',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            AnnouncementUserDomainObjectAbstract::ANNOUNCEMENT_ID,
            AnnouncementUserDomainObjectAbstract::USER_ID,
            AnnouncementUserDomainObjectAbstract::FIRST_SEEN_AT,
            AnnouncementUserDomainObjectAbstract::DISMISSED_AT,
        ];
    }
}
