<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\Enums\HomepageSectionType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSettingDomainObject
 */
class EventSettingsResourcePublic extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly bool $includePostCheckoutData = false,
        private readonly bool $offersSmsOptIn = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'pre_checkout_message' => $this->getPreCheckoutMessage(),

            $this->mergeWhen($this->includePostCheckoutData, [
                'post_checkout_message' => $this->getPostCheckoutMessage(),
            ]),

            'product_page_message' => $this->getProductPageMessage(),
            'continue_button_text' => $this->getContinueButtonText(),
            'get_tickets_button_text' => $this->getGetTicketsButtonText(),
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

            // Ticket design settings
            'ticket_design_settings' => $this->getTicketDesignSettings(),

            // SEO settings
            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription(),
            'seo_keywords' => $this->getSeoKeywords(),
            'allow_search_engine_indexing' => $this->getAllowSearchEngineIndexing(),

            'price_display_mode' => $this->getPriceDisplayMode(),

            // Payment settings
            'payment_providers' => $this->getPaymentProviders(),
            'offline_payment_instructions' => $this->getOfflinePaymentInstructions(),
            'allow_orders_awaiting_offline_payment_to_check_in' => $this->getAllowOrdersAwaitingOfflinePaymentToCheckIn(),
            'offline_payment_reporting_enabled' => $this->getOfflinePaymentReportingEnabled(),
            'offline_payment_order_reference_enabled' => $this->getOfflinePaymentOrderReferenceEnabled(),
            'payment_links_enabled' => $this->getPaymentLinksEnabled(),
            'payment_link_expiry_hours' => $this->getPaymentLinkExpiryHours(),

            // Invoice settings
            'require_billing_address' => $this->getRequireBillingAddress(),
            'invoice_label' => $this->getInvoiceLabel(),

            // Marketing settings
            'show_marketing_opt_in' => $this->getShowMarketingOptIn(),
            /** @var 'OFF'|'OPTIONAL'|'REQUIRED' */
            'phone_collection' => $this->getPhoneCollection(),
            /** @var bool */
            'show_sms_opt_in' => $this->offersSmsOptIn,

            // Attendee detail copy control
            'allow_copy_details_to_all_attendees' => $this->getAllowCopyDetailsToAllAttendees(),

            // Platform fee settings
            'pass_platform_fee_to_buyer' => $this->getPassPlatformFeeToBuyer(),

            // Homepage theme settings
            'homepage_theme_settings' => $this->getHomepageThemeSettings(),
            'homepage_sections' => HomepageSectionType::publicSections($this->getHomepageSections()),

            // Self-service settings
            'allow_attendee_self_edit' => $this->getAllowAttendeeSelfEdit(),
            'allow_ticket_transfer' => $this->getAllowTicketTransfer(),
            'ticket_transfer_cutoff_minutes' => $this->getTicketTransferCutoffMinutes(),
            'max_transfers_per_ticket' => $this->getMaxTransfersPerTicket(),
            'allow_ticket_resale' => $this->getAllowTicketResale(),
            'ticket_resale_cutoff_minutes' => $this->getTicketResaleCutoffMinutes(),

            // Occurrence display
            'show_available_occurrence_capacity' => $this->getShowAvailableOccurrenceCapacity(),
            'hide_sold_out_occurrences' => $this->getHideSoldOutOccurrences(),

            // Waitlist settings
            'waitlist_auto_process' => $this->getWaitlistAutoProcess(),
            'waitlist_offer_timeout_minutes' => $this->getWaitlistOfferTimeoutMinutes(),
        ];
    }
}
