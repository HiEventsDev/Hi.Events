<?php

namespace HiEvents\Service\Handler\EventSettings;

use Throwable;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Http\DataTransferObjects\PartialUpdateEventSettingsDTO;
use HiEvents\Http\DataTransferObjects\UpdateEventSettingsDTO;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;

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

        return $this->eventSettingsHandler->handle(
            UpdateEventSettingsDTO::fromArray([
                'event_id' => $eventSettingsDTO->event_id,
                'account_id' => $eventSettingsDTO->account_id,
                'ticket_page_message' => $eventSettingsDTO->settings['ticket_page_message'] ?? $existingSettings->getTicketPageMessage(),
                'post_checkout_message' => $eventSettingsDTO->settings['post_checkout_message'] ?? $existingSettings->getPostCheckoutMessage(),
                'pre_checkout_message' => $eventSettingsDTO->settings['pre_checkout_message'] ?? $existingSettings->getPreCheckoutMessage(),
                'email_footer_message' => $eventSettingsDTO->settings['email_footer_message'] ?? $existingSettings->getEmailFooterMessage(),
                'reply_to_email' => $eventSettingsDTO->settings['reply_to_email'] ?? $existingSettings->getReplyToEmail(),
                'require_attendee_details' => $eventSettingsDTO->settings['require_attendee_details'] ?? $existingSettings->getRequireAttendeeDetails(),
                'continue_button_text' => $eventSettingsDTO->settings['continue_button_text'] ?? $existingSettings->getContinueButtonText(),

                'homepage_background_color' => $eventSettingsDTO->settings['homepage_background_color'] ?? $existingSettings->getHomepageBackgroundColor(),
                'homepage_primary_color' => $eventSettingsDTO->settings['homepage_primary_color'] ?? $existingSettings->getHomepagePrimaryColor(),
                'homepage_primary_text_color' => $eventSettingsDTO->settings['homepage_primary_text_color'] ?? $existingSettings->getHomepagePrimaryTextColor(),
                'homepage_secondary_color' => $eventSettingsDTO->settings['homepage_secondary_color'] ?? $existingSettings->getHomepageSecondaryColor(),
                'homepage_secondary_text_color' => $eventSettingsDTO->settings['homepage_secondary_text_color'] ?? $existingSettings->getHomepageSecondaryTextColor(),

                'order_timeout_in_minutes' => $eventSettingsDTO->settings['order_timeout_in_minutes'] ?? $existingSettings->getOrderTimeoutInMinutes(),
                'website_url' => $eventSettingsDTO->settings['website_url'] ?? $existingSettings->getWebsiteUrl(),
                'maps_url' => $eventSettingsDTO->settings['maps_url'] ?? $existingSettings->getMapsUrl(),
                'location_details' => $eventSettingsDTO->settings['location_details'] ?? $existingSettings->getLocationDetails(),
                'is_online_event' => $eventSettingsDTO->settings['is_online_event'] ?? $existingSettings->getIsOnlineEvent(),
                'online_event_connection_details' => $eventSettingsDTO->settings['online_event_connection_details'] ?? $existingSettings->getOnlineEventConnectionDetails(),

                'seo_title' => $eventSettingsDTO->settings['seo_title'] ?? $existingSettings->getSeoTitle(),
                'seo_description' => $eventSettingsDTO->settings['seo_description'] ?? $existingSettings->getSeoDescription(),
                'seo_keywords' => $eventSettingsDTO->settings['seo_keywords'] ?? $existingSettings->getSeoKeywords(),
                'allow_search_engine_indexing' => $eventSettingsDTO->settings['allow_search_engine_indexing'] ?? $existingSettings->getAllowSearchEngineIndexing(),

                'notify_organizer_of_new_orders' => $eventSettingsDTO->settings['notify_organizer_of_new_orders'] ?? $existingSettings->getNotifyOrganizerOfNewOrders(),
            ]),
        );
    }
}
