<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateEventService
{
    public function __construct(
        private readonly EventRepositoryInterface          $eventRepository,
        private readonly EventSettingsRepositoryInterface  $eventSettingsRepository,
        private readonly OrganizerRepositoryInterface      $organizerRepository,
        private readonly DatabaseManager                   $databaseManager,
        private readonly EventStatisticRepositoryInterface $eventStatisticsRepository,
        private readonly HTMLPurifier                      $purifier,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function createEvent(
        EventDomainObject        $eventData,
        EventSettingDomainObject $eventSettings = null
    ): EventDomainObject
    {
        $this->databaseManager->beginTransaction();

        $organizer = $this->getOrganizer(
            organizerId: $eventData->getOrganizerId(),
            accountId: $eventData->getAccountId()
        );

        $event = $this->handleEventCreate($eventData);

        $this->createEventSettings(
            eventSettings: $eventSettings,
            event: $event,
            organizer: $organizer
        );

        $this->createEventStatistics($event);

        $this->databaseManager->commit();

        return $event;
    }

    /**
     * @throws OrganizerNotFoundException
     */
    private function getOrganizer(int $organizerId, int $accountId): OrganizerDomainObject
    {
        $organizer = $this->organizerRepository->findFirstWhere([
            'id' => $organizerId,
            'account_id' => $accountId,
        ]);

        if ($organizer === null) {
            throw new OrganizerNotFoundException(
                __('Organizer :id not found', ['id' => $organizerId])
            );
        }

        return $organizer;
    }

    private function handleEventCreate(EventDomainObject $eventData): EventDomainObject
    {
        return $this->eventRepository->create([
            'title' => $eventData->getTitle(),
            'organizer_id' => $eventData->getOrganizerId(),
            'start_date' => DateHelper::convertToUTC($eventData->getStartDate(), $eventData->getTimezone()),
            'end_date' => $eventData->getEndDate()
                ? DateHelper::convertToUTC($eventData->getEndDate(), $eventData->getTimezone())
                : null,
            'description' => $this->purifier->purify($eventData->getDescription()),
            'timezone' => $eventData->getTimezone(),
            'currency' => $eventData->getCurrency(),
            'location_details' => $eventData->getLocationDetails(),
            'account_id' => $eventData->getAccountId(),
            'user_id' => $eventData->getUserId(),
            'status' => $eventData->getStatus(),
            'short_id' => IdHelper::shortId(IdHelper::EVENT_PREFIX),
            'attributes' => $eventData->getAttributes(),
        ]);
    }

    private function createEventStatistics(EventDomainObject $event): void
    {
        $this->eventStatisticsRepository->create([
            'event_id' => $event->getId(),
            'tickets_sold' => 0,
            'sales_total_gross' => 0,
            'sales_total_before_additions' => 0,
            'total_tax' => 0,
            'total_fee' => 0,
            'orders_created' => 0,
        ]);
    }

    private function createEventSettings(
        ?EventSettingDomainObject $eventSettings,
        EventDomainObject         $event,
        OrganizerDomainObject     $organizer
    ): void
    {
        if ($eventSettings !== null) {
            $eventSettings->setEventId($event->getId());
            $eventSettingsArray = $eventSettings->toArray();

            unset($eventSettingsArray['id']);

            $this->eventSettingsRepository->create($eventSettingsArray);

            return;
        }

        $this->eventSettingsRepository->create([
            'event_id' => $event->getId(),
            'homepage_background_color' => '#ffffff',
            'homepage_primary_text_color' => '#000000',
            'homepage_primary_color' => '#7b5db8',
            'homepage_secondary_text_color' => '#ffffff',
            'homepage_secondary_color' => '#7b5eb9',
            'homepage_background_type' => HomepageBackgroundType::COLOR->name,
            'homepage_body_background_color' => '#7a5eb9',
            'continue_button_text' => __('Continue'),
            'support_email' => $organizer->getEmail(),
        ]);
    }
}
