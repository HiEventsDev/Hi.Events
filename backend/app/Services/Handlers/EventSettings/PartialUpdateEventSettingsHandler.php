<?php

namespace HiEvents\Services\Handlers\EventSettings;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Handlers\EventSettings\DTO\PartialUpdateEventSettingsDTO;
use HiEvents\Services\Handlers\EventSettings\DTO\UpdateEventSettingsDTO;
use Throwable;

readonly class PartialUpdateEventSettingsHandler
{
    public function __construct(
        private UpdateEventSettingsHandler       $eventSettingsHandler,
        private EventSettingsRepositoryInterface $eventSettingsRepository,
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

        $locationDetails = $eventSettingsDTO->settings['location_details'] ?? $existingSettings->getLocationDetails();
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
                'maps_url' => $eventSettingsDTO->settings['maps_url'] ?? $existingSettings->getMapsUrl(),
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
            ]),
        );
    }
}
