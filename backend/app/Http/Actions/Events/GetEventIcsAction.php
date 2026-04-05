<?php

namespace HiEvents\Http\Actions\Events;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Helper\StringHelper;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\EventStatus;

class GetEventIcsAction extends BaseAction
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): Response
    {
        $event = $this->eventRepository
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($eventId);

        $calendar = Calendar::create()
            ->name($event->getTitle())
            ->refreshInterval(60)
            ->productIdentifier('-//Hi.Events//Event Calendar//EN');

        if ($event->getStartDate()) {
            $startDateTime = Carbon::parse($event->getStartDate(), $event->getTimezone());
            $endDateTime = $event->getEndDate()
                ? Carbon::parse($event->getEndDate(), $event->getTimezone())
                : null;

            $icsEvent = Event::create()
                ->name($event->getTitle())
                ->uniqueIdentifier('event-' . $event->getId())
                ->startsAt($startDateTime)
                ->url($event->getEventUrl())
                ->status(EventStatus::confirmed());

            if ($event->getDescription()) {
                $icsEvent->description(StringHelper::previewFromHtml($event->getDescription(), 1000));
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
            'Content-Disposition' => sprintf('inline; filename="%s.ics"', $event->getSlug()),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
