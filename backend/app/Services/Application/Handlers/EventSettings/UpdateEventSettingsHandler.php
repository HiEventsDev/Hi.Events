<?php

namespace HiEvents\Services\Application\Handlers\EventSettings;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\UpdateEventSettingsDTO;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateEventSettingsHandler
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly HtmlPurifierService              $purifier,
        private readonly DatabaseManager                  $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpdateEventSettingsDTO $settings): EventSettingDomainObject
    {
        return $this->databaseManager->transaction(function () use ($settings) {
            $this->eventSettingsRepository->updateWhere(
                attributes: [
                    'post_checkout_message' => $settings->post_checkout_message
                        ?? $this->purifier->purify($settings->post_checkout_message),
                    'pre_checkout_message' => $settings->pre_checkout_message
                        ?? $this->purifier->purify($settings->pre_checkout_message),
                    'email_footer_message' => $settings->email_footer_message
                        ?? $this->purifier->purify($settings->email_footer_message),
                    'support_email' => $settings->support_email,
                    'require_attendee_details' => $settings->require_attendee_details,
                    'continue_button_text' => trim($settings->continue_button_text),

                    'homepage_background_color' => $settings->homepage_background_color,
                    'homepage_primary_color' => $settings->homepage_primary_color,
                    'homepage_primary_text_color' => $settings->homepage_primary_text_color,
                    'homepage_secondary_color' => $settings->homepage_secondary_color,
                    'homepage_secondary_text_color' => $settings->homepage_secondary_text_color,
                    'homepage_body_background_color' => $settings->homepage_body_background_color,
                    'homepage_background_type' => $settings->homepage_background_type->name,

                    'order_timeout_in_minutes' => $settings->order_timeout_in_minutes,
                    'website_url' => trim($settings->website_url),
                    'maps_url' => trim($settings->maps_url),
                    'location_details' => $settings->location_details?->toArray(),
                    'is_online_event' => $settings->is_online_event,
                    'online_event_connection_details' => $settings->online_event_connection_details
                        ?? $this->purifier->purify($settings->online_event_connection_details),

                    'seo_title' => $settings->seo_title,
                    'seo_description' => $settings->seo_description,
                    'seo_keywords' => $settings->seo_keywords,
                    'allow_search_engine_indexing' => $settings->allow_search_engine_indexing,
                    'notify_organizer_of_new_orders' => $settings->notify_organizer_of_new_orders,
                    'price_display_mode' => $settings->price_display_mode->name,
                    'hide_getting_started_page' => $settings->hide_getting_started_page,

                    // Payment settings
                    'payment_providers' => $settings->payment_providers,
                    'offline_payment_instructions' => $settings->offline_payment_instructions
                        ?? $this->purifier->purify($settings->offline_payment_instructions),
                    'allow_orders_awaiting_offline_payment_to_check_in' => $settings->allow_orders_awaiting_offline_payment_to_check_in,

                    // Invoice settings
                    'enable_invoicing' => $settings->enable_invoicing,
                    'invoice_label' => trim($settings->invoice_label),
                    'invoice_prefix' => trim($settings->invoice_prefix),
                    'invoice_start_number' => $settings->invoice_start_number,
                    'require_billing_address' => $settings->require_billing_address,
                    'organization_name' => trim($settings->organization_name),
                    'organization_address' => $this->purifier->purify($settings->organization_address),
                    'invoice_tax_details' => $this->purifier->purify($settings->invoice_tax_details),
                    'invoice_notes' => $this->purifier->purify($settings->invoice_notes),
                    'invoice_payment_terms_days' => $settings->invoice_payment_terms_days,
                    
                    // Ticket design settings
                    'ticket_design_settings' => $settings->ticket_design_settings,
                ],
                where: [
                    'event_id' => $settings->event_id,
                ],
            );

            return $this->eventSettingsRepository
                ->findFirstWhere([
                    'event_id' => $settings->event_id,
                ]);
        });
    }
}
