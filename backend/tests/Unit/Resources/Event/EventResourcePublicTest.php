<?php

namespace Tests\Unit\Resources\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Resources\Event\EventResourcePublic;
use Illuminate\Http\Request;
use Tests\TestCase;

class EventResourcePublicTest extends TestCase
{
    public function test_public_resource_does_not_recap_occurrences_after_handler_preserves_linked_occurrence(): void
    {
        $occurrences = collect(range(1, 200))
            ->map(fn (int $id) => $this->makeOccurrence($id))
            ->push($this->makeOccurrence(250));

        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(1)
            ->setUserId(1)
            ->setTitle('Recurring show')
            ->setShortId('event')
            ->setType(EventType::RECURRING->name)
            ->setCurrency('USD')
            ->setTimezone('UTC')
            ->setCreatedAt('2026-01-01 00:00:00')
            ->setUpdatedAt('2026-01-01 00:00:00')
            ->setEventOccurrences($occurrences);

        $payload = (new EventResourcePublic($event))->toArray(new Request);
        $resolvedOccurrences = $payload['occurrences']->resolve(new Request);

        $this->assertCount(201, $resolvedOccurrences);
        $this->assertTrue(
            collect($resolvedOccurrences)->contains(fn (array $occurrence) => $occurrence['id'] === 250)
        );
    }

    public function test_public_resource_keeps_past_hidden_occurrence_for_single_event(): void
    {
        $pastOccurrence = $this->makeOccurrence(10)
            ->setStartDate('2026-01-01 10:00:00')
            ->setEndDate('2026-01-01 11:00:00');

        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(1)
            ->setUserId(1)
            ->setTitle('Single show')
            ->setShortId('event')
            ->setType(EventType::SINGLE->name)
            ->setCurrency('USD')
            ->setTimezone('UTC')
            ->setCreatedAt('2026-01-01 00:00:00')
            ->setUpdatedAt('2026-01-01 00:00:00')
            ->setEventOccurrences(collect([$pastOccurrence]));

        $payload = (new EventResourcePublic($event))->toArray(new Request);
        $resolvedOccurrences = $payload['occurrences']->resolve(new Request);

        $this->assertCount(1, $resolvedOccurrences);
        $this->assertSame(10, $resolvedOccurrences[0]['id']);
    }

    private function makeOccurrence(int $id): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)
            ->setId($id)
            ->setEventId(1)
            ->setShortId((string) $id)
            ->setStartDate('2027-01-01 10:00:00')
            ->setEndDate('2027-01-01 11:00:00')
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);
    }
}
