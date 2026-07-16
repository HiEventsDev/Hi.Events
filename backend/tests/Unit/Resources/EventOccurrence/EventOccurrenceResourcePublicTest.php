<?php

namespace Tests\Unit\Resources\EventOccurrence;

use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use Illuminate\Http\Request;
use Tests\TestCase;

class EventOccurrenceResourcePublicTest extends TestCase
{
    private function occurrence(?bool $override): EventOccurrenceDomainObject
    {
        $occurrence = new EventOccurrenceDomainObject;
        $occurrence->setId(1);
        $occurrence->setEventId(1);
        $occurrence->setStartDate('2026-07-10 10:00:00');
        $occurrence->setStatus('ACTIVE');
        $occurrence->setCapacity(100);
        $occurrence->setUsedCapacity(10);
        $occurrence->setShowAvailableCapacity($override);

        return $occurrence;
    }

    private function serialize(EventOccurrenceDomainObject $occurrence, ?bool $eventDefault): array
    {
        $resource = $eventDefault === null
            ? new EventOccurrenceResourcePublic($occurrence)
            : new EventOccurrenceResourcePublic($occurrence, $eventDefault);

        return $resource->resolve(Request::create('/'));
    }

    public function test_legacy_consumer_without_event_context_hides_capacity(): void
    {
        $result = $this->serialize($this->occurrence(null), null);

        $this->assertArrayNotHasKey('available_capacity', $result);
        $this->assertArrayNotHasKey('capacity', $result);
    }

    public function test_override_show_without_event_context_shows_capacity(): void
    {
        $result = $this->serialize($this->occurrence(true), null);

        $this->assertArrayHasKey('available_capacity', $result);
        $this->assertSame(90, $result['available_capacity']);
    }

    public function test_event_default_hidden_omits_capacity(): void
    {
        $result = $this->serialize($this->occurrence(null), false);

        $this->assertArrayNotHasKey('available_capacity', $result);
        $this->assertArrayNotHasKey('capacity', $result);
    }

    public function test_event_default_shown_includes_capacity(): void
    {
        $result = $this->serialize($this->occurrence(null), true);

        $this->assertArrayHasKey('available_capacity', $result);
        $this->assertSame(90, $result['available_capacity']);
    }

    public function test_occurrence_override_show_wins_over_hidden_default(): void
    {
        $result = $this->serialize($this->occurrence(true), false);

        $this->assertArrayHasKey('available_capacity', $result);
    }

    public function test_occurrence_override_hide_wins_over_shown_default(): void
    {
        $result = $this->serialize($this->occurrence(false), true);

        $this->assertArrayNotHasKey('available_capacity', $result);
        $this->assertArrayNotHasKey('capacity', $result);
    }

    public function test_status_and_label_are_always_present(): void
    {
        $occurrence = $this->occurrence(null);
        $occurrence->setLabel('Morning');

        $result = $this->serialize($occurrence, false);

        $this->assertSame('ACTIVE', $result['status']);
        $this->assertSame('Morning', $result['label']);
    }

    private function onlineLocation(): EventLocationDomainObject
    {
        $eventLocation = new EventLocationDomainObject;
        $eventLocation->setType('ONLINE');
        $eventLocation->setOnlineEventConnectionDetails('<p>Zoom link</p>');

        return $eventLocation;
    }

    public function test_event_location_is_absent_when_not_loaded(): void
    {
        $result = $this->serialize($this->occurrence(null), null);

        $this->assertArrayNotHasKey('event_location', $result);
    }

    public function test_in_person_event_location_includes_venue(): void
    {
        $location = new LocationDomainObject;
        $location->setName('Grand Hall');
        $location->setStructuredAddress(['venue_name' => 'Grand Hall', 'city' => 'Dublin']);

        $eventLocation = new EventLocationDomainObject;
        $eventLocation->setType('IN_PERSON');
        $eventLocation->setLocation($location);

        $occurrence = $this->occurrence(null);
        $occurrence->setEventLocation($eventLocation);

        $result = json_decode(json_encode($this->serialize($occurrence, null)), true);

        $this->assertSame('IN_PERSON', $result['event_location']['type']);
        $this->assertSame('Grand Hall', $result['event_location']['location']['name']);
        $this->assertSame('Dublin', $result['event_location']['location']['structured_address']['city']);
    }

    public function test_online_connection_details_are_hidden_by_default(): void
    {
        $occurrence = $this->occurrence(null);
        $occurrence->setEventLocation($this->onlineLocation());

        $result = json_decode(json_encode($this->serialize($occurrence, null)), true);

        $this->assertSame('ONLINE', $result['event_location']['type']);
        $this->assertArrayNotHasKey('online_event_connection_details', $result['event_location']);
    }

    public function test_online_connection_details_are_included_when_flagged(): void
    {
        $occurrence = $this->occurrence(null);
        $occurrence->setEventLocation($this->onlineLocation());

        $resource = new EventOccurrenceResourcePublic($occurrence, false, true);
        $result = json_decode(json_encode($resource->resolve(Request::create('/'))), true);

        $this->assertSame('<p>Zoom link</p>', $result['event_location']['online_event_connection_details']);
    }
}
