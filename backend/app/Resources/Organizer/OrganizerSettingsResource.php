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
            /** @var 'OFF'|'OPTIONAL'|'REQUIRED' */
            'default_phone_collection' => $this->getDefaultPhoneCollection(),
            'default_show_sms_opt_in' => $this->getDefaultShowSmsOptIn(),
            'default_pass_platform_fee_to_buyer' => $this->getDefaultPassPlatformFeeToBuyer(),
            'default_allow_attendee_self_edit' => $this->getDefaultAllowAttendeeSelfEdit(),
            'default_allow_ticket_transfer' => $this->getDefaultAllowTicketTransfer(),
            'default_allow_ticket_resale' => $this->getDefaultAllowTicketResale(),
            'social_media_handles' => $this->getSocialMediaHandles(),
            'homepage_theme_settings' => $this->getHomepageThemeSettings(),
            'homepage_sections' => $this->getHomepageSections(),
            'homepage_visibility' => $this->getHomepageVisibility(),
            'homepage_password' => $this->getHomepagePassword(),
            'website_url' => $this->getWebsiteUrl(),
            'seo_keywords' => $this->getSeoKeywords(),
            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription(),
            'allow_search_engine_indexing' => $this->getAllowSearchEngineIndexing(),
            'tracking_pixels' => $this->getTrackingPixels(),
            'tracking_consent_acknowledged' => $this->getTrackingConsentAcknowledged(),
            'first_day_of_week' => $this->getFirstDayOfWeek(),
        ];
    }
}
