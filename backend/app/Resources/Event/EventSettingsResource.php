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
            'online_event_connection_details' => $this->getOnlineEventConnectionDetails(),

            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription(),
            'seo_keywords' => $this->getSeoKeywords(),
            'allow_search_engine_indexing' => $this->getAllowSearchEngineIndexing(),

            'notify_organizer_of_new_orders' => $this->getNotifyOrganizerOfNewOrders(),

            'price_display_mode' => $this->getPriceDisplayMode(),
            'hide_getting_started_page' => $this->getHideGettingStartedPage(),

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
        ];
    }
}
