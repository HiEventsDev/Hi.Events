<?php

namespace HiEvents\Services\Handlers\EventSettings\DTO;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DataTransferObjects\BaseDTO;

class UpdateEventSettingsDTO extends BaseDTO
{
    public function __construct(
        public readonly int         $account_id,

        // event settings
        public readonly int         $event_id,
        public readonly ?string     $post_checkout_message,
        public readonly ?string     $pre_checkout_message,
        public readonly ?string     $email_footer_message,
        public readonly ?string     $continue_button_text,
        public readonly ?string     $reply_to_email,

        public readonly ?string     $homepage_background_color,
        public readonly ?string     $homepage_primary_color,
        public readonly ?string     $homepage_primary_text_color,
        public readonly ?string     $homepage_secondary_color,
        public readonly ?string     $homepage_secondary_text_color,

        public readonly bool        $require_attendee_details,
        public readonly int         $order_timeout_in_minutes,
        public readonly ?string     $website_url,
        public readonly ?string     $maps_url,
        public readonly ?string     $seo_title,
        public readonly ?string     $seo_description,
        public readonly ?string     $seo_keywords,

        public readonly ?AddressDTO $location_details = null,
        public readonly bool        $is_online_event = false,
        public readonly ?string     $online_event_connection_details = null,

        public readonly ?bool       $allow_search_engine_indexing = true,

        public readonly ?bool       $notify_organizer_of_new_orders = null,
    )
    {
    }
}
