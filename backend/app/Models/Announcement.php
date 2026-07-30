<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\AnnouncementDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends BaseModel
{
    use SoftDeletes;

    public function announcementUsers(): HasMany
    {
        return $this->hasMany(AnnouncementUser::class);
    }

    protected function getCastMap(): array
    {
        return [
            AnnouncementDomainObjectAbstract::TARGET_ACCOUNT_IDS => 'array',
            AnnouncementDomainObjectAbstract::TARGET_USER_IDS => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            AnnouncementDomainObjectAbstract::TITLE,
            AnnouncementDomainObjectAbstract::CONTENT,
            AnnouncementDomainObjectAbstract::STATUS,
            AnnouncementDomainObjectAbstract::DISPLAY_TYPE,
            AnnouncementDomainObjectAbstract::EMOJI,
            AnnouncementDomainObjectAbstract::TARGET_TYPE,
            AnnouncementDomainObjectAbstract::TARGET_ACCOUNT_IDS,
            AnnouncementDomainObjectAbstract::TARGET_USER_IDS,
            AnnouncementDomainObjectAbstract::CTA_LABEL,
            AnnouncementDomainObjectAbstract::CTA_URL,
        ];
    }
}
