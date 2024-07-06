<?php

namespace HiEvents\Services\Handlers\EventSettings\DTO;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\PriceDisplayMode;
use HiEvents\DomainObjects\OrganizerDomainObject;

class UpdateEventSettingsDTO extends BaseDTO
{
    public function __construct(
        public readonly int                     $account_id,

        // event settings
        public readonly int                     $event_id,
        public readonly ?string                 $post_checkout_message,
        public readonly ?string                 $pre_checkout_message,
        public readonly ?string                 $email_footer_message,
        public readonly ?string                 $continue_button_text,
        public readonly ?string                 $support_email,

        public readonly ?string                 $homepage_background_color,
        public readonly ?string                 $homepage_primary_color,
        public readonly ?string                 $homepage_primary_text_color,
        public readonly ?string                 $homepage_secondary_color,
        public readonly ?string                 $homepage_secondary_text_color,
        public readonly ?string                 $homepage_body_background_color,
        public readonly ?HomepageBackgroundType $homepage_background_type,

        public readonly bool                    $require_attendee_details,
        public readonly int                     $order_timeout_in_minutes,
        public readonly ?string                 $website_url,
        public readonly ?string                 $maps_url,
        public readonly ?string                 $seo_title,
        public readonly ?string                 $seo_description,
        public readonly ?string                 $seo_keywords,

        public readonly ?AddressDTO             $location_details = null,
        public readonly bool                    $is_online_event = false,
        public readonly ?string                 $online_event_connection_details = null,

        public readonly ?bool                   $allow_search_engine_indexing = true,

        public readonly ?bool                   $notify_organizer_of_new_orders = null,

        public readonly ?PriceDisplayMode       $price_display_mode = PriceDisplayMode::INCLUSIVE,

        public readonly ?bool                   $hide_getting_started_page = false,
    )
    {
    }

    public static function createWithDefaults(
        int                   $account_id,
        int                   $event_id,
        OrganizerDomainObject $organizer,
    ): self
    {
        return new self(
            account_id: $account_id,
            event_id: $event_id,
            post_checkout_message: null,
            pre_checkout_message: null,
            email_footer_message: null,
            continue_button_text: __('Continue'),
            support_email: $organizer->getEmail(),
            homepage_background_color: '#ffffff',
            homepage_primary_color: '#7b5db8',
            homepage_primary_text_color: '#000000',
            homepage_secondary_color: '#7b5eb9',
            homepage_secondary_text_color: '#ffffff',
            homepage_body_background_color: '#7a5eb9',
            homepage_background_type: HomepageBackgroundType::COLOR,
            require_attendee_details: false,
            order_timeout_in_minutes: 0,
            website_url: null,
            maps_url: null,
            seo_title: null,
            seo_description: null,
            seo_keywords: null,
            location_details: null,
            is_online_event: false,
            online_event_connection_details: null,
            allow_search_engine_indexing: true,
            notify_organizer_of_new_orders: null,
            price_display_mode: PriceDisplayMode::INCLUSIVE,
            hide_getting_started_page: false,
        );
    }
}
