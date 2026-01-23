<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin OrganizerSettingDomainObject
 */
class OrganizerSettingsResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'organizer_id' => $this->getOrganizerId(),
            'default_attendee_details_collection_method' => $this->getDefaultAttendeeDetailsCollectionMethod(),
            'default_show_marketing_opt_in' => $this->getDefaultShowMarketingOptIn(),
            'default_pass_platform_fee_to_buyer' => $this->getDefaultPassPlatformFeeToBuyer(),
            'default_allow_attendee_self_edit' => $this->getDefaultAllowAttendeeSelfEdit(),
            'social_media_handles' => $this->getSocialMediaHandles(),
            'homepage_theme_settings' => $this->getHomepageThemeSettings(),
            'homepage_visibility' => $this->getHomepageVisibility(),
            'homepage_password' => $this->getHomepagePassword(),
            'website_url' => $this->getWebsiteUrl(),
            'seo_keywords' => $this->getSeoKeywords(),
            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription(),
            'allow_search_engine_indexing' => $this->getAllowSearchEngineIndexing(),
            'location_details' => $this->getLocationDetails(),
        ];
    }
}
