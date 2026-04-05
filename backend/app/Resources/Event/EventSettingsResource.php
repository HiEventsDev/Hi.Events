<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventSettingDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSettingDomainObject
 */
class EventSettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'pre_checkout_message' => $this->getPreCheckoutMessage(),
            'post_checkout_message' => $this->getPostCheckoutMessage(),
            'product_page_message' => $this->getProductPageMessage(),
            'continue_button_text' => $this->getContinueButtonText(),
            'required_attendee_details' => $this->getRequireAttendeeDetails(),
            'attendee_details_collection_method' => $this->getAttendeeDetailsCollectionMethod(),
            'email_footer_message' => $this->getEmailFooterMessage(),
            'support_email' => $this->getSupportEmail(),
            'order_timeout_in_minutes' => $this->getOrderTimeoutInMinutes(),

            'homepage_body_background_color' => $this->getHomepageBodyBackgroundColor(),
            'homepage_background_color' => $this->getHomepageBackgroundColor(),
            'homepage_primary_color' => $this->getHomepagePrimaryColor(),
            'homepage_primary_text_color' => $this->getHomepagePrimaryTextColor(),
            'homepage_secondary_color' => $this->getHomepageSecondaryColor(),
            'homepage_secondary_text_color' => $this->getHomepageSecondaryTextColor(),
            'homepage_background_type' => $this->getHomepageBackgroundType(),

            'website_url' => $this->getWebsiteUrl(),
            'maps_url' => $this->getMapsUrl(),

            'location_details' => $this->getLocationDetails(),
            'is_online_event' => $this->getIsOnlineEvent(),
            'event_location_type' => $this->getEventLocationType(),
            'online_event_connection_details' => $this->getOnlineEventConnectionDetails(),

            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription(),
            'seo_keywords' => $this->getSeoKeywords(),
            'allow_search_engine_indexing' => $this->getAllowSearchEngineIndexing(),

            'notify_organizer_of_new_orders' => $this->getNotifyOrganizerOfNewOrders(),
            'disable_attendee_ticket_email' => $this->getDisableAttendeeTicketEmail(),

            'price_display_mode' => $this->getPriceDisplayMode(),
            'hide_getting_started_page' => $this->getHideGettingStartedPage(),
            'hide_start_date' => $this->getHideStartDate(),

            // Ticket design settings
            'ticket_design_settings' => $this->getTicketDesignSettings(),

            // Payment settings
            'payment_providers' => $this->getPaymentProviders(),
            'offline_payment_instructions' => $this->getOfflinePaymentInstructions(),
            'allow_orders_awaiting_offline_payment_to_check_in' => $this->getAllowOrdersAwaitingOfflinePaymentToCheckIn(),

            // Invoice settings
            'enable_invoicing' => $this->getEnableInvoicing(),
            'invoice_label' => $this->getInvoiceLabel(),
            'invoice_prefix' => $this->getInvoicePrefix(),
            'invoice_start_number' => $this->getInvoiceStartNumber(),
            'require_billing_address' => $this->getRequireBillingAddress(),
            'organization_name' => $this->getOrganizationName(),
            'organization_address' => $this->getOrganizationAddress(),
            'invoice_tax_details' => $this->getInvoiceTaxDetails(),
            'invoice_notes' => $this->getInvoiceNotes(),
            'invoice_payment_terms_days' => $this->getInvoicePaymentTermsDays(),

            // Marketing settings
            'show_marketing_opt_in' => $this->getShowMarketingOptIn(),

            // Platform fee settings
            'pass_platform_fee_to_buyer' => $this->getPassPlatformFeeToBuyer(),

            // Homepage theme settings
            'homepage_theme_settings' => $this->getHomepageThemeSettings(),

            // Self-service settings
            'allow_attendee_self_edit' => $this->getAllowAttendeeSelfEdit(),

            // Waitlist settings
            'waitlist_auto_process' => $this->getWaitlistAutoProcess(),
            'waitlist_offer_timeout_minutes' => $this->getWaitlistOfferTimeoutMinutes(),

            // Social media settings
            'social_media_handles' => $this->getSocialMediaHandles(),
            'show_social_media_handles' => $this->getShowSocialMediaHandles(),

            // Access control settings
            'event_password' => $this->getEventPassword(),

            // Payment settings
            'stripe_payment_method_order' => $this->getStripePaymentMethodOrder(),

            // Order approval settings
            'require_order_approval' => $this->getRequireOrderApproval(),
            'external_ticket_url' => $this->getExternalTicketUrl(),

            // Order-level ticket quantity limits
            'order_min_tickets' => $this->getOrderMinTickets(),
            'order_max_tickets' => $this->getOrderMaxTickets(),

            // Checkout validation webhook
            'checkout_validation_webhook_url' => $this->getCheckoutValidationWebhookUrl(),

            // Attendee name requirement
            'require_attendee_name' => $this->getRequireAttendeeName(),

            // Free ticket expiration
            'free_ticket_expiration_minutes' => $this->getFreeTicketExpirationMinutes(),
        ];
    }
}
