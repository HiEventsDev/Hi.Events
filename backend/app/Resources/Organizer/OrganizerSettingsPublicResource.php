<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\OrganizerSettingDomainObject;

/**
 * @mixin OrganizerSettingDomainObject
 */
class OrganizerSettingsPublicResource extends OrganizerSettingsResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        unset(
            $data['tracking_consent_acknowledged'],
            $data['homepage_password'],
        );

        return $data;
    }
}
