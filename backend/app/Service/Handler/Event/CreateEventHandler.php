<?php

declare(strict_types=1);

namespace TicketKitten\Service\Handler\Event;

use Illuminate\Database\DatabaseManager;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Exceptions\OrganizerNotFoundException;
use TicketKitten\Helper\DateHelper;
use TicketKitten\Helper\IdHelper;
use TicketKitten\Http\DataTransferObjects\AttributesDTO;
use TicketKitten\Http\DataTransferObjects\CreateEventDTO;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventSettingsRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrganizerRepositoryInterface;

readonly class CreateEventHandler
{
    public function __construct(
        private EventRepositoryInterface         $eventRepository,
        private EventSettingsRepositoryInterface $eventSettingsRepository,
        private OrganizerRepositoryInterface     $organizerRepository,
        private DatabaseManager                  $databaseManager,
    )
    {
    }

    /**
     * @throws OrganizerNotFoundException
     * @throws Throwable
     */
    public function handle(CreateEventDTO $eventData): EventDomainObject
    {
        return $this->databaseManager->transaction(fn() => $this->createEvent(
            eventData: $eventData,
            organizer: $this->getOrganizer($eventData)
        ));
    }

    private function getEventDataWithDefaults(
        CreateEventDTO        $eventData,
        OrganizerDomainObject $organizer
    ): CreateEventDTO
    {
        return new CreateEventDTO(
            title: $eventData->title,
            organizer_id: $eventData->organizer_id,
            account_id: $eventData->account_id,
            user_id: $eventData->user_id,
            start_date: $eventData->start_date,
            end_date: $eventData->end_date,
            description: $eventData->description,
            attributes: $eventData->attributes,
            timezone: $eventData->timezone ?? $organizer->getTimezone(),
            currency: $eventData->currency ?? $organizer->getCurrency(),
            location_details: $eventData->location_details,
            status: $eventData->status,
        );
    }

    /**
     * @throws OrganizerNotFoundException
     */
    private function getOrganizer(CreateEventDTO $eventData): OrganizerDomainObject
    {
        $organizer = $this->organizerRepository->findFirstWhere([
            'id' => $eventData->organizer_id,
            'account_id' => $eventData->account_id,
        ]);

        if ($organizer === null) {
            throw new OrganizerNotFoundException(
                __('Organizer :id not found', ['id' => $eventData->organizer_id])
            );
        }

        return $organizer;
    }

    private function createEvent(CreateEventDTO $eventData, OrganizerDomainObject $organizer): EventDomainObject
    {
        $eventData = $this->getEventDataWithDefaults($eventData, $organizer);

        $event = $this->eventRepository->create([
            'title' => $eventData->title,
            'organizer_id' => $eventData->organizer_id,
            'start_date' => DateHelper::convertToUTC($eventData->start_date, $eventData->timezone),
            'end_date' => $eventData->end_date
                ? DateHelper::convertToUTC($eventData->end_date, $eventData->timezone)
                : null,
            'description' => $eventData->description,
            'timezone' => $eventData->timezone,
            'currency' => $eventData->currency,
            'location_details' => $eventData->location_details?->toArray(),
            'account_id' => $eventData->account_id,
            'user_id' => $eventData->user_id,
            'status' => $eventData->status,
            'short_id' => IdHelper::randomPrefixedId(IdHelper::EVENT_PREFIX),
            'attributes' => $eventData->attributes?->map(fn(AttributesDTO $attr) => $attr->toArray())->toArray(),
        ]);

        $this->eventSettingsRepository->create([
            'event_id' => $event->getId(),
            'homepage_background_color' => '#ffffff',
            'homepage_primary_text_color' => '#000000',
            'homepage_primary_color' => '#7b5db8',
            'homepage_secondary_text_color' => '#ffffff',
            'homepage_secondary_color' => '#7b5eb9',
            'continue_button_text' => __('Continue'),
        ]);

        return $event;
    }
}
