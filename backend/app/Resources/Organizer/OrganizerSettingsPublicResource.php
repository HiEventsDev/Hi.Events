<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\Enums\TrackingPixelProvider;
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

        if (config('app.saas_mode_enabled') && !empty($data['tracking_pixels'])) {
            $data['tracking_pixels'] = array_values(array_filter(
                $data['tracking_pixels'],
                fn($pixel) => ($pixel['provider'] ?? null) !== TrackingPixelProvider::GOOGLE_TAG_MANAGER->value,
            ));
        }

        return $data;
    }
}
