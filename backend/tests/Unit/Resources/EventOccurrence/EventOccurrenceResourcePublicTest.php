<?php

namespace Tests\Unit\Resources\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
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
}
