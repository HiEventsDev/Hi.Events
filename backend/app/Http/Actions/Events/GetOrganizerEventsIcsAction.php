<?php

namespace HiEvents\Http\Actions\Events;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Helper\StringHelper;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use HiEvents\Repository\Eloquent\Value\Relationship;

class GetOrganizerEventsIcsAction extends BaseAction
{
    public function __construct(
        private readonly EventRepositoryInterface     $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
    )
    {
    }

    public function __invoke(int $organizerId, Request $request): Response
    {
        $organizer = $this->organizerRepository->findById($organizerId);

        $events = $this->eventRepository
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findWhere([
                'organizer_id' => $organizerId,
                'status' => EventStatus::LIVE->name,
            ]);

        $calendar = Calendar::create()
            ->name($organizer->getName() . ' Events')
            ->refreshInterval(60);

        /** @var EventDomainObject $event */
        foreach ($events as $event) {
            if (!$event->getStartDate()) {
                continue;
            }

            $startDateTime = Carbon::parse($event->getStartDate(), $event->getTimezone());
            $endDateTime = $event->getEndDate()
                ? Carbon::parse($event->getEndDate(), $event->getTimezone())
                : null;

            $icsEvent = Event::create()
                ->name($event->getTitle())
                ->uniqueIdentifier('event-' . $event->getId())
                ->startsAt($startDateTime)
                ->url($event->getEventUrl());

            if ($event->getDescription()) {
                $icsEvent->description(StringHelper::previewFromHtml($event->getDescription(), 500));
            }

            /** @var EventSettingDomainObject|null $settings */
            $settings = $event->getEventSettings();
            if ($settings?->getLocationDetails()) {
                $icsEvent->address($settings->getAddressString());
            }

            if ($endDateTime) {
                $icsEvent->endsAt($endDateTime);
            }

            $calendar->event($icsEvent);
        }

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="events.ics"',
        ]);
    }
}
