<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventSettingDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSettingDomainObject
 */
class EventSettingsResourcePublic extends JsonResource
{
    public function __construct(
        mixed                 $resource,
        private readonly bool $includePostCheckoutData = false,
    )
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'pre_checkout_message' => $this->getPreCheckoutMessage(),

            // We only show post checkout data if the order is completed. So this data is only returned when this
            // resource is returned within the context of an order that is completed.
            // i.e. order->event->event_settings and not event->event_settings
            $this->mergeWhen($this->includePostCheckoutData, [
                'post_checkout_message' => $this->getPostCheckoutMessage(),
                'online_event_connection_details' => $this->getOnlineEventConnectionDetails(),
            ]),

            'product_page_message' => $this->getProductPageMessage(),
            'continue_button_text' => $this->getContinueButtonText(),
            'required_attendee_details' => $this->getRequireAttendeeDetails(),
            'attendee_details_collection_method' => $this->getAttendeeDetailsCollectionMethod(),
            'email_footer_message' => $this->getEmailFooterMessage(),
            'support_email' => $this->getSupportEmail(),
            'order_timeout_in_minutes' => $this->getOrderTimeoutInMinutes(),

            // Homepage settings
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

            // Ticket design settings
            'ticket_design_settings' => $this->getTicketDesignSettings(),

            // SEO settings
            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription(),
            'seo_keywords' => $this->getSeoKeywords(),
            'allow_search_engine_indexing' => $this->getAllowSearchEngineIndexing(),

            'price_display_mode' => $this->getPriceDisplayMode(),
            'hide_start_date' => $this->getHideStartDate(),

            // Payment settings
            'payment_providers' => $this->getPaymentProviders(),
            'offline_payment_instructions' => $this->getOfflinePaymentInstructions(),
            'allow_orders_awaiting_offline_payment_to_check_in' => $this->getAllowOrdersAwaitingOfflinePaymentToCheckIn(),

            // Invoice settings
            'require_billing_address' => $this->getRequireBillingAddress(),
            'invoice_label' => $this->getInvoiceLabel(),

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
            'is_password_protected' => !empty($this->getEventPassword()),

            // Payment settings
            'stripe_payment_method_order' => $this->getStripePaymentMethodOrder(),

            // Order approval settings
            'require_order_approval' => $this->getRequireOrderApproval(),
            'external_ticket_url' => $this->getExternalTicketUrl(),

            // Order-level ticket quantity limits
            'order_min_tickets' => $this->getOrderMinTickets(),
            'order_max_tickets' => $this->getOrderMaxTickets(),

            // Attendee name requirement
            'require_attendee_name' => $this->getRequireAttendeeName(),

            // Free ticket expiration
            'free_ticket_expiration_minutes' => $this->getFreeTicketExpirationMinutes(),
        ];
    }
}
