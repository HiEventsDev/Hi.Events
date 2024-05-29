<?php

namespace HiEvents\Services\Handlers\EventSettings;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Handlers\EventSettings\DTO\UpdateEventSettingsDTO;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class UpdateEventSettingsHandler
{
    public function __construct(
        private EventSettingsRepositoryInterface $eventSettingsRepository,
        private HTMLPurifier                     $purifier,
        private DatabaseManager                  $databaseManager,
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
