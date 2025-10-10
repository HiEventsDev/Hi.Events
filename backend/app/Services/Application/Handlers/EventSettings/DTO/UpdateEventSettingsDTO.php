<?php

namespace HiEvents\Services\Application\Handlers\EventSettings\DTO;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
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

        // Payment settings
        public readonly array                   $payment_providers = [],
        public readonly ?string                 $offline_payment_instructions = null,
        public readonly bool                    $allow_orders_awaiting_offline_payment_to_check_in = false,

        // Invoice settings
        public readonly bool                    $enable_invoicing = false,
        public readonly ?string                 $invoice_label = null,
        public readonly ?string                 $invoice_prefix = null,
        public readonly ?int                    $invoice_start_number = null,
        public readonly bool                    $require_billing_address = true,
        public readonly ?string                 $organization_name = null,
        public readonly ?string                 $organization_address = null,
        public readonly ?string                 $invoice_tax_details = null,
        public readonly ?string                 $invoice_notes = null,
        public readonly ?int                    $invoice_payment_terms_days = null,

        // Ticket design settings
        public readonly ?array                  $ticket_design_settings = null,
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

            // Payment defaults
            payment_providers: [PaymentProviders::STRIPE->value],
            offline_payment_instructions: null,

            // Invoice defaults
            enable_invoicing: false,
            invoice_label: __('Invoice'),
            invoice_prefix: null,
            invoice_start_number: 1,
            require_billing_address: true,
            organization_name: $organizer->getName(),
            organization_address: null,
            invoice_tax_details: null,
            invoice_notes: null,
            invoice_payment_terms_days: null,

            // Ticket design defaults
            ticket_design_settings: [
                'accent_color' => '#333333',
                'logo_image_id' => null,
                'footer_text' => null,
                'layout_type' => 'classic',
                'enabled' => true,
            ],
        );
    }
}
