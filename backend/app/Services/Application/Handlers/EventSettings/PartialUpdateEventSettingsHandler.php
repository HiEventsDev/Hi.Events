<?php

namespace HiEvents\Services\Application\Handlers\EventSettings;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\PartialUpdateEventSettingsDTO;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\UpdateEventSettingsDTO;
use Throwable;

class PartialUpdateEventSettingsHandler
{
    public function __construct(
        private readonly UpdateEventSettingsHandler       $eventSettingsHandler,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(PartialUpdateEventSettingsDTO $eventSettingsDTO): EventSettingDomainObject
    {
        $existingSettings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $eventSettingsDTO->event_id,
        ]);

        if (!$existingSettings) {
            throw new RefundNotPossibleException('Event settings not found');
        }

        $locationDetails = AddressDTO::from($eventSettingsDTO->settings['location_details'] ?? $existingSettings->getLocationDetails());
        $isOnlineEvent = $eventSettingsDTO->settings['is_online_event'] ?? $existingSettings->getIsOnlineEvent();

        if ($isOnlineEvent) {
            $locationDetails = null;
        }

        return $this->eventSettingsHandler->handle(
            UpdateEventSettingsDTO::fromArray([
                'event_id' => $eventSettingsDTO->event_id,
                'account_id' => $eventSettingsDTO->account_id,
                'post_checkout_message' => array_key_exists('post_checkout_message', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['post_checkout_message']
                    : $existingSettings->getPostCheckoutMessage(),
                'pre_checkout_message' => array_key_exists('pre_checkout_message', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['pre_checkout_message']
                    : $existingSettings->getPreCheckoutMessage(),
                'email_footer_message' => $eventSettingsDTO->settings['email_footer_message'] ?? $existingSettings->getEmailFooterMessage(),
                'support_email' => $eventSettingsDTO->settings['support_email'] ?? $existingSettings->getSupportEmail(),
                'require_attendee_details' => $eventSettingsDTO->settings['require_attendee_details'] ?? $existingSettings->getRequireAttendeeDetails(),
                'continue_button_text' => array_key_exists('continue_button_text', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['continue_button_text']
                    : $existingSettings->getContinueButtonText(),

                'homepage_background_color' => $eventSettingsDTO->settings['homepage_background_color'] ?? $existingSettings->getHomepageBackgroundColor(),
                'homepage_primary_color' => $eventSettingsDTO->settings['homepage_primary_color'] ?? $existingSettings->getHomepagePrimaryColor(),
                'homepage_primary_text_color' => $eventSettingsDTO->settings['homepage_primary_text_color'] ?? $existingSettings->getHomepagePrimaryTextColor(),
                'homepage_secondary_color' => $eventSettingsDTO->settings['homepage_secondary_color'] ?? $existingSettings->getHomepageSecondaryColor(),
                'homepage_secondary_text_color' => $eventSettingsDTO->settings['homepage_secondary_text_color'] ?? $existingSettings->getHomepageSecondaryTextColor(),
                'homepage_body_background_color' => $eventSettingsDTO->settings['homepage_body_background_color'] ?? $existingSettings->getHomepageBodyBackgroundColor(),
                'homepage_background_type' => $eventSettingsDTO->settings['homepage_background_type'] ?? $existingSettings->getHomepageBackgroundType(),

                'order_timeout_in_minutes' => $eventSettingsDTO->settings['order_timeout_in_minutes'] ?? $existingSettings->getOrderTimeoutInMinutes(),
                'website_url' => $eventSettingsDTO->settings['website_url'] ?? $existingSettings->getWebsiteUrl(),
                'maps_url' => array_key_exists('maps_url', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['maps_url']
                    : $existingSettings->getMapsUrl(),
                'location_details' => $locationDetails,
                'is_online_event' => $eventSettingsDTO->settings['is_online_event'] ?? $existingSettings->getIsOnlineEvent(),
                'online_event_connection_details' => array_key_exists('online_event_connection_details', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['online_event_connection_details']
                    : $existingSettings->getOnlineEventConnectionDetails(),

                'seo_title' => $eventSettingsDTO->settings['seo_title'] ?? $existingSettings->getSeoTitle(),
                'seo_description' => $eventSettingsDTO->settings['seo_description'] ?? $existingSettings->getSeoDescription(),
                'seo_keywords' => $eventSettingsDTO->settings['seo_keywords'] ?? $existingSettings->getSeoKeywords(),
                'allow_search_engine_indexing' => $eventSettingsDTO->settings['allow_search_engine_indexing'] ?? $existingSettings->getAllowSearchEngineIndexing(),

                'notify_organizer_of_new_orders' => $eventSettingsDTO->settings['notify_organizer_of_new_orders'] ?? $existingSettings->getNotifyOrganizerOfNewOrders(),
                'price_display_mode' => $eventSettingsDTO->settings['price_display_mode'] ?? $existingSettings->getPriceDisplayMode(),
                'hide_getting_started_page' => $eventSettingsDTO->settings['hide_getting_started_page'] ?? $existingSettings->getHideGettingStartedPage(),

                // Payment settings
                'payment_providers' => $eventSettingsDTO->settings['payment_providers'] ?? $existingSettings->getPaymentProviders(),
                'offline_payment_instructions' => array_key_exists('offline_payment_instructions', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['offline_payment_instructions']
                    : $existingSettings->getOfflinePaymentInstructions(),
                'allow_orders_awaiting_offline_payment_to_check_in' => $eventSettingsDTO->settings['allow_orders_awaiting_offline_payment_to_check_in']
                    ?? $existingSettings->getAllowOrdersAwaitingOfflinePaymentToCheckIn(),

                // Invoice settings
                'enable_invoicing' => $eventSettingsDTO->settings['enable_invoicing'] ?? $existingSettings->getEnableInvoicing(),
                'invoice_label' => array_key_exists('invoice_label', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['invoice_label']
                    : $existingSettings->getInvoiceLabel(),
                'invoice_prefix' => array_key_exists('invoice_prefix', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['invoice_prefix']
                    : $existingSettings->getInvoicePrefix(),
                'invoice_start_number' => $eventSettingsDTO->settings['invoice_start_number'] ?? $existingSettings->getInvoiceStartNumber(),
                'require_billing_address' => $eventSettingsDTO->settings['require_billing_address'] ?? $existingSettings->getRequireBillingAddress(),
                'organization_name' => array_key_exists('organization_name', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['organization_name']
                    : $existingSettings->getOrganizationName(),
                'organization_address' => array_key_exists('organization_address', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['organization_address']
                    : $existingSettings->getOrganizationAddress(),
                'invoice_tax_details' => array_key_exists('invoice_tax_details', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['invoice_tax_details']
                    : $existingSettings->getInvoiceTaxDetails(),
                'invoice_notes' => array_key_exists('invoice_notes', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['invoice_notes']
                    : $existingSettings->getInvoiceNotes(),
                'invoice_payment_terms_days' => array_key_exists('invoice_payment_terms_days', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['invoice_payment_terms_days']
                    : $existingSettings->getInvoicePaymentTermsDays(),

                // Ticket design settings
                'ticket_design_settings' => array_key_exists('ticket_design_settings', $eventSettingsDTO->settings)
                    ? $eventSettingsDTO->settings['ticket_design_settings']
                    : $existingSettings->getTicketDesignSettings()
            ]),
        );
    }
}
