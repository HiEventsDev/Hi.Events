<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\EventLocation;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\UpdateEventOccurrenceHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end canary for the event_location pivot. Exercises hydration,
 * occurrence inherit/override semantics, orphan cleanup, and the
 * pre/post-checkout gate on online_event_connection_details.
 */
class EventLocationCanaryTest extends TestCase
{
    use DatabaseTransactions;

    private EventRepositoryInterface $eventRepo;

    private EventOccurrenceRepositoryInterface $occurrenceRepo;

    private EventLocationRepositoryInterface $eventLocationRepo;

    private int $accountId;

    private int $organizerId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventRepo = $this->app->make(EventRepositoryInterface::class);
        $this->occurrenceRepo = $this->app->make(EventOccurrenceRepositoryInterface::class);
        $this->eventLocationRepo = $this->app->make(EventLocationRepositoryInterface::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $now = now()->toDateTimeString();

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Canary Organizer',
            'email' => 'canary@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_event_in_person_default_resolves_with_venue_via_event_location_relation(): void
    {
        $venueId = $this->createVenue('Main Venue', ['city' => 'Dublin']);
        $eventLocationId = $this->createEventLocation(
            eventId: $eventId = $this->createEvent('In-Person Event'),
            type: LocationType::IN_PERSON,
            locationId: $venueId,
        );
        $this->linkEventLocationToEvent($eventId, $eventLocationId);

        $event = $this->eventRepo
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findFirstWhere(['id' => $eventId]);

        $payload = (new EventResource($event))->toArray(new Request);

        $this->assertArrayHasKey('event_location', $payload);
        $eventLocation = $payload['event_location']->toArray(new Request);
        $this->assertSame(LocationType::IN_PERSON->name, $eventLocation['type']);
        $this->assertArrayHasKey('location', $eventLocation);
        $nestedLocation = $eventLocation['location']->toArray(new Request);
        $this->assertSame($venueId, $nestedLocation['id']);
    }

    public function test_occurrence_inherits_event_location_when_null(): void
    {
        // The inheritance walk lives in the email/service layer, so an
        // occurrence with no override must not surface event_location here.
        $venueId = $this->createVenue('Event Venue');
        $eventId = $this->createEvent('Event with Inherited Occurrence');
        $eventLocationId = $this->createEventLocation($eventId, LocationType::IN_PERSON, $venueId);
        $this->linkEventLocationToEvent($eventId, $eventLocationId);

        $occurrenceId = $this->createOccurrence($eventId, eventLocationId: null);

        $occurrence = $this->occurrenceRepo
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findFirstWhere([EventOccurrenceDomainObjectAbstract::ID => $occurrenceId]);

        $payload = (new EventOccurrenceResource($occurrence))->toArray(new Request);

        // Absent values come back as Laravel's MissingValue, not as null.
        $this->assertTrue(
            ! array_key_exists('event_location', $payload)
                || $payload['event_location'] instanceof \Illuminate\Http\Resources\MissingValue,
            'Inheriting occurrence must not carry its own event_location on the resource',
        );
    }

    public function test_occurrence_in_person_override_returned_in_resource(): void
    {
        $eventVenueId = $this->createVenue('Event Default Venue');
        $overrideVenueId = $this->createVenue('Override Venue');
        $eventId = $this->createEvent('Event with Override Occurrence');

        $eventEventLocationId = $this->createEventLocation($eventId, LocationType::IN_PERSON, $eventVenueId);
        $this->linkEventLocationToEvent($eventId, $eventEventLocationId);

        $overrideEventLocationId = $this->createEventLocation($eventId, LocationType::IN_PERSON, $overrideVenueId);
        $occurrenceId = $this->createOccurrence($eventId, eventLocationId: $overrideEventLocationId);

        $occurrence = $this->occurrenceRepo
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findFirstWhere([EventOccurrenceDomainObjectAbstract::ID => $occurrenceId]);

        $payload = (new EventOccurrenceResource($occurrence))->toArray(new Request);

        $this->assertArrayHasKey('event_location', $payload);
        $embed = $payload['event_location']->toArray(new Request);
        $this->assertSame(LocationType::IN_PERSON->name, $embed['type']);
        $this->assertSame($overrideVenueId, $embed['location']->toArray(new Request)['id']);
    }

    public function test_online_event_default_with_online_occurrence_override(): void
    {
        $eventId = $this->createEvent('Online Event');

        $eventEventLocationId = $this->createEventLocation(
            eventId: $eventId,
            type: LocationType::ONLINE,
            onlineDetails: '<p>Event default Zoom</p>',
        );
        $this->linkEventLocationToEvent($eventId, $eventEventLocationId);

        $occurrenceEventLocationId = $this->createEventLocation(
            eventId: $eventId,
            type: LocationType::ONLINE,
            onlineDetails: '<p>Occurrence override Zoom</p>',
        );
        $occurrenceId = $this->createOccurrence($eventId, eventLocationId: $occurrenceEventLocationId);

        // Event-level
        $event = $this->eventRepo
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location'))
            ->findFirstWhere(['id' => $eventId]);

        $eventPayload = (new EventResource($event))->toArray(new Request);
        $eventEmbed = $eventPayload['event_location']->toArray(new Request);
        $this->assertSame(LocationType::ONLINE->name, $eventEmbed['type']);
        $this->assertSame('<p>Event default Zoom</p>', $eventEmbed['online_event_connection_details']);

        // Occurrence-level
        $occurrence = $this->occurrenceRepo
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location'))
            ->findFirstWhere([EventOccurrenceDomainObjectAbstract::ID => $occurrenceId]);

        $occPayload = (new EventOccurrenceResource($occurrence))->toArray(new Request);
        $occEmbed = $occPayload['event_location']->toArray(new Request);
        $this->assertSame(LocationType::ONLINE->name, $occEmbed['type']);
        $this->assertSame('<p>Occurrence override Zoom</p>', $occEmbed['online_event_connection_details']);
    }

    public function test_clear_event_location_on_occurrence_orphans_cleaned_up(): void
    {
        // clear_event_location must null the FK AND soft-delete the now-
        // unreferenced row via EventLocationCleaner.
        $venueId = $this->createVenue('Override Venue');
        $eventId = $this->createEvent('Event with Cleanable Override');
        $occurrenceEventLocationId = $this->createEventLocation($eventId, LocationType::IN_PERSON, $venueId);
        $occurrenceId = $this->createOccurrence($eventId, eventLocationId: $occurrenceEventLocationId);

        // Pre-condition: row exists and is not soft-deleted.
        $this->assertDatabaseHas('event_locations', [
            'id' => $occurrenceEventLocationId,
            'deleted_at' => null,
        ]);

        $handler = $this->app->make(UpdateEventOccurrenceHandler::class);
        $handler->handle(
            $occurrenceId,
            new UpsertEventOccurrenceDTO(
                event_id: $eventId,
                start_date: now()->addDay()->toDateTimeString(),
                end_date: now()->addDay()->addHours(2)->toDateTimeString(),
                clear_event_location: true,
            ),
        );

        // Occurrence FK is now null.
        $occurrence = $this->occurrenceRepo->findFirstWhere([EventOccurrenceDomainObjectAbstract::ID => $occurrenceId]);
        $this->assertNull($occurrence->getEventLocationId());

        // The orphaned row is soft-deleted.
        $stillAlive = DB::table('event_locations')
            ->where('id', $occurrenceEventLocationId)
            ->whereNull('deleted_at')
            ->exists();
        $this->assertFalse($stillAlive, 'Orphaned event_location row should be soft-deleted by EventLocationCleaner');
    }

    public function test_public_event_resource_hides_internal_location_fields(): void
    {
        // Public surface must never leak internal IDs, organizer scoping,
        // provider names, place IDs or timestamps from the venue row.
        $venueId = $this->createVenue('Public Venue', ['city' => 'Dublin']);
        DB::table('locations')->where('id', $venueId)->update([
            'provider' => 'google',
            'provider_place_id' => 'ChIJsecretplaceid',
        ]);
        $eventId = $this->createEvent('In-Person Public Event');
        $eventLocationId = $this->createEventLocation($eventId, LocationType::IN_PERSON, $venueId);
        $this->linkEventLocationToEvent($eventId, $eventLocationId);

        $event = $this->eventRepo
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findFirstWhere(['id' => $eventId]);

        $payload = (new EventResourcePublic($event, false))->toArray(new Request);
        $eventLocation = $payload['event_location']->toArray(new Request);
        $location = $eventLocation['location']->toArray(new Request);

        $this->assertSame('Public Venue', $location['name']);
        $this->assertSame('Dublin', $location['structured_address']['city']);

        foreach (['id', 'organizer_id', 'provider', 'provider_place_id', 'created_at', 'updated_at'] as $sensitive) {
            $this->assertArrayNotHasKey($sensitive, $location, "$sensitive must not appear in the public location payload");
        }
    }

    public function test_public_event_resource_hides_online_connection_details_pre_checkout(): void
    {
        // online_event_connection_details is gated until the buyer completes
        // their order — pre-checkout responses must omit it entirely.
        $eventId = $this->createEvent('Online Public Event');
        $eventLocationId = $this->createEventLocation(
            eventId: $eventId,
            type: LocationType::ONLINE,
            onlineDetails: '<p>Secret Zoom: https://zoom.example/secret</p>',
        );
        $this->linkEventLocationToEvent($eventId, $eventLocationId);

        $event = $this->eventRepo
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location'))
            ->findFirstWhere(['id' => $eventId]);

        // Pre-checkout
        $prePayload = (new EventResourcePublic($event, false))->toArray(new Request);
        $preEmbed = $prePayload['event_location']->toArray(new Request);
        $preAssertable = $this->resolveWhen($preEmbed);
        $this->assertArrayNotHasKey('online_event_connection_details', $preAssertable);

        // Post-checkout
        $postPayload = (new EventResourcePublic($event, true))->toArray(new Request);
        $postEmbed = $postPayload['event_location']->toArray(new Request);
        $postAssertable = $this->resolveWhen($postEmbed);
        $this->assertArrayHasKey('online_event_connection_details', $postAssertable);
        $this->assertSame('<p>Secret Zoom: https://zoom.example/secret</p>', $postAssertable['online_event_connection_details']);
    }

    // Strips MissingValue instances so array_key_exists assertions work.
    private function resolveWhen(array $payload): array
    {
        return array_filter(
            $payload,
            fn ($value) => ! ($value instanceof \Illuminate\Http\Resources\MissingValue),
        );
    }

    private function createVenue(string $name, array $structuredAddress = []): int
    {
        return DB::table('locations')->insertGetId([
            'short_id' => 'loc_'.uniqid(),
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => $name,
            'structured_address' => json_encode(array_merge(['venue_name' => $name], $structuredAddress)),
            'latitude' => 53.3478,
            'longitude' => -6.2289,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEvent(string $title): int
    {
        return DB::table('events')->insertGetId([
            'title' => $title,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEventLocation(
        int $eventId,
        LocationType $type,
        ?int $locationId = null,
        ?string $onlineDetails = null,
    ): int {
        return DB::table('event_locations')->insertGetId([
            'short_id' => 'el_'.uniqid(),
            'event_id' => $eventId,
            'type' => $type->name,
            'location_id' => $locationId,
            'online_event_connection_details' => $onlineDetails,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function linkEventLocationToEvent(int $eventId, int $eventLocationId): void
    {
        DB::table('events')->where('id', $eventId)->update([
            'event_location_id' => $eventLocationId,
        ]);
    }

    private function createOccurrence(int $eventId, ?int $eventLocationId): int
    {
        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'event_location_id' => $eventLocationId,
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date' => now()->addDay()->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
