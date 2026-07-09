<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Services\Domain\Email\EmailTokenContextBuilder;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class EmailTokenContextBuilderTest extends TestCase
{
    private EmailTokenContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextBuilder = app(EmailTokenContextBuilder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_builds_order_confirmation_context(): void
    {
        $order = $this->createMockOrder();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order,
            $event,
            $organizer,
            $eventSettings
        );

        $this->assertIsArray($context);
        $this->assertArrayHasKey('order', $context);
        $this->assertArrayHasKey('event', $context);
        $this->assertArrayHasKey('organizer', $context);
        $this->assertArrayHasKey('settings', $context);

        // Test order context
        $this->assertEquals('ORD-123456', $context['order']['number']);
        $this->assertEquals('$9,999.00', $context['order']['total']);
        $this->assertEquals('John', $context['order']['first_name']);
        $this->assertEquals('Doe', $context['order']['last_name']);
        $this->assertEquals('john@example.com', $context['order']['email']);

        // Test event context
        $this->assertEquals('Amazing Event', $context['event']['title']);
        $this->assertEquals('This is an amazing event', $context['event']['description']);

        // Test organizer context
        $this->assertEquals('Great Organizer', $context['organizer']['name']);
        $this->assertEquals('contact@organizer.com', $context['organizer']['email']);

        // Test settings context
        $this->assertEquals('support@event.com', $context['settings']['support_email']);
    }

    public function test_builds_attendee_ticket_context(): void
    {
        $attendee = $this->createMockAttendee();
        $order = $this->createMockOrder();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $context = $this->contextBuilder->buildAttendeeTicketContext(
            $attendee,
            $order,
            $event,
            $organizer,
            $eventSettings
        );

        $this->assertIsArray($context);
        $this->assertArrayHasKey('attendee', $context);
        $this->assertArrayHasKey('ticket', $context);
        $this->assertArrayHasKey('order', $context);
        $this->assertArrayHasKey('event', $context);
        $this->assertArrayHasKey('organizer', $context);

        // Test attendee context
        $this->assertEquals('Jane Smith', $context['attendee']['name']);
        $this->assertEquals('jane@example.com', $context['attendee']['email']);

        // Test ticket context
        $this->assertEquals('General Admission', $context['ticket']['name']);
        $this->assertEquals('$4,999.00', $context['ticket']['price']);

        // Test event context
        $this->assertEquals('Amazing Event', $context['event']['title']);

        // Test organizer context
        $this->assertEquals('Great Organizer', $context['organizer']['name']);
    }

    public function test_whitelists_only_allowed_tokens_for_order_confirmation(): void
    {
        $order = $this->createMockOrder();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order,
            $event,
            $organizer,
            $eventSettings
        );

        $this->assertArrayHasKey('order', $context);
        $this->assertArrayHasKey('event', $context);
        $this->assertArrayHasKey('organizer', $context);
        $this->assertArrayHasKey('settings', $context);

        $this->assertArrayHasKey('number', $context['order']);
        $this->assertArrayHasKey('first_name', $context['order']);
        $this->assertArrayHasKey('title', $context['event']);
    }

    public function test_whitelists_only_allowed_tokens_for_attendee_ticket(): void
    {
        $attendee = $this->createMockAttendee();
        $order = $this->createMockOrder();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $context = $this->contextBuilder->buildAttendeeTicketContext(
            $attendee,
            $order,
            $event,
            $organizer,
            $eventSettings
        );

        $this->assertArrayHasKey('attendee', $context);
        $this->assertArrayHasKey('ticket', $context);
        $this->assertArrayHasKey('event', $context);
        $this->assertArrayHasKey('organizer', $context);

        $this->assertArrayHasKey('name', $context['attendee']);
        $this->assertArrayHasKey('name', $context['ticket']);
        $this->assertArrayHasKey('title', $context['event']);
    }

    public function test_occurrence_dates_override_event_dates(): void
    {
        $order = $this->createMockOrder();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();
        $occurrence = $this->createMockOccurrence();

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order, $event, $organizer, $eventSettings, $occurrence
        );

        $this->assertArrayHasKey('occurrence', $context);
        $this->assertNotEmpty($context['occurrence']['start_date']);
        $this->assertNotEmpty($context['occurrence']['start_time']);
        $this->assertNotEmpty($context['occurrence']['end_date']);
        $this->assertNotEmpty($context['occurrence']['end_time']);
        $this->assertEquals('Afternoon Show', $context['occurrence']['label']);
        $this->assertStringContainsString('Afternoon Show', $context['event']['title']);
    }

    public function test_occurrence_tokens_empty_when_no_occurrence(): void
    {
        $order = $this->createMockOrder();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order, $event, $organizer, $eventSettings
        );

        $this->assertArrayHasKey('occurrence', $context);
        $this->assertEquals('', $context['occurrence']['label']);
    }

    public function test_event_in_person_location_in_token_context(): void
    {
        // Event has an in-person EventLocation with a venue → the location
        // section of the context should reflect IN_PERSON with structured
        // address and no online connection details.
        $order = $this->createMockOrder();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $venue = $this->makeVenueLocation(
            name: 'The Arena',
            structuredAddress: [
                'venue_name' => 'The Arena',
                'address_line_1' => '1 Stadium Way',
                'city' => 'Dublin',
                'country' => 'IE',
            ],
            latitude: 53.3478,
            longitude: -6.2289,
        );
        $eventLocation = $this->makeEventLocation(
            type: LocationType::IN_PERSON,
            location: $venue,
            label: 'Main entrance',
        );

        $event = $this->createMockEventWithLocation($eventLocation);

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order, $event, $organizer, $eventSettings
        );

        $this->assertArrayHasKey('event_location', $context);
        $this->assertSame(LocationType::IN_PERSON->name, $context['event_location']['type']);
        $this->assertFalse($context['event_location']['is_online']);
        $this->assertNull($context['event_location']['online_connection_details']);
        $this->assertSame('The Arena', $context['event_location']['name']);
        $this->assertIsArray($context['event_location']['structured_address']);
        $this->assertNotEmpty($context['event_location']['formatted_address']);

        // The legacy event.location_details token is still expected to surface
        // the same structured address.
        $this->assertSame(
            $context['event_location']['structured_address'],
            $context['event']['location_details'],
        );
    }

    public function test_event_online_location_in_token_context(): void
    {
        // Online event → is_online flips true and online_connection_details
        // surfaces. No structured address.
        $order = $this->createMockOrder();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $eventLocation = $this->makeEventLocation(
            type: LocationType::ONLINE,
            location: null,
            onlineDetails: '<p>Zoom: example.zoom.us/j/123</p>',
        );

        $event = $this->createMockEventWithLocation($eventLocation);

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order, $event, $organizer, $eventSettings
        );

        $this->assertSame(LocationType::ONLINE->name, $context['event_location']['type']);
        $this->assertTrue($context['event_location']['is_online']);
        $this->assertSame(
            '<p>Zoom: example.zoom.us/j/123</p>',
            $context['event_location']['online_connection_details'],
        );
        $this->assertNull($context['event_location']['structured_address']);
        $this->assertNull($context['event']['location_details']);
    }

    public function test_occurrence_event_location_overrides_event_event_location(): void
    {
        // Occurrence has its own EventLocation override → the resolver walk
        // (`$occurrence->getEventLocation() ?? $event->getEventLocation()`)
        // must pick the occurrence's, not the event's.
        $order = $this->createMockOrder();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $eventVenue = $this->makeVenueLocation(
            name: 'Event Default Venue',
            structuredAddress: ['venue_name' => 'Event Default Venue'],
        );
        $eventEventLocation = $this->makeEventLocation(
            type: LocationType::IN_PERSON,
            location: $eventVenue,
        );
        $event = $this->createMockEventWithLocation($eventEventLocation);

        $occVenue = $this->makeVenueLocation(
            name: 'Occurrence Override Venue',
            structuredAddress: ['venue_name' => 'Occurrence Override Venue'],
        );
        $occEventLocation = $this->makeEventLocation(
            type: LocationType::IN_PERSON,
            location: $occVenue,
        );
        $occurrence = $this->createMockOccurrenceWithLocation($occEventLocation);

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order, $event, $organizer, $eventSettings, $occurrence
        );

        $this->assertSame('Occurrence Override Venue', $context['event_location']['name']);
        $this->assertSame(
            ['venue_name' => 'Occurrence Override Venue'],
            $context['event_location']['structured_address'],
        );
    }

    public function test_occurrence_inherits_event_event_location_when_null(): void
    {
        // Occurrence has no event_location override → the resolver walk falls
        // through to the event's EventLocation.
        $order = $this->createMockOrder();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $eventVenue = $this->makeVenueLocation(
            name: 'Event Default Venue',
            structuredAddress: ['venue_name' => 'Event Default Venue'],
        );
        $eventEventLocation = $this->makeEventLocation(
            type: LocationType::IN_PERSON,
            location: $eventVenue,
        );
        $event = $this->createMockEventWithLocation($eventEventLocation);

        $occurrence = $this->createMockOccurrenceWithLocation(null);

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order, $event, $organizer, $eventSettings, $occurrence
        );

        $this->assertSame('Event Default Venue', $context['event_location']['name']);
    }

    public function test_safe_fallback_when_event_location_and_occurrence_location_both_null(): void
    {
        // Both event and occurrence have no EventLocation → the builder
        // emits a context with nullable fields rather than crashing.
        $order = $this->createMockOrder();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $event = $this->createMockEventWithLocation(null);
        $occurrence = $this->createMockOccurrenceWithLocation(null);

        $context = $this->contextBuilder->buildOrderConfirmationContext(
            $order, $event, $organizer, $eventSettings, $occurrence
        );

        $this->assertArrayHasKey('event_location', $context);
        $this->assertNull($context['event_location']['type']);
        $this->assertFalse($context['event_location']['is_online']);
        $this->assertNull($context['event_location']['online_connection_details']);
        $this->assertNull($context['event_location']['name']);
        $this->assertNull($context['event_location']['structured_address']);
        $this->assertNull($context['event']['location_details']);
    }

    private function makeVenueLocation(
        string $name = 'A Venue',
        ?array $structuredAddress = null,
        ?float $latitude = null,
        ?float $longitude = null,
    ): LocationDomainObject {
        $loc = Mockery::mock(LocationDomainObject::class);
        $loc->shouldReceive('getName')->andReturn($name);
        $loc->shouldReceive('getStructuredAddress')->andReturn($structuredAddress);
        $loc->shouldReceive('getLatitude')->andReturn($latitude);
        $loc->shouldReceive('getLongitude')->andReturn($longitude);

        return $loc;
    }

    private function makeEventLocation(
        LocationType $type,
        ?LocationDomainObject $location = null,
        ?string $onlineDetails = null,
        ?string $label = null,
    ): EventLocationDomainObject {
        $el = Mockery::mock(EventLocationDomainObject::class);
        $el->shouldReceive('getType')->andReturn($type->name);
        $el->shouldReceive('getLocation')->andReturn($location);
        $el->shouldReceive('getOnlineEventConnectionDetails')->andReturn($onlineDetails);
        $el->shouldReceive('getLabel')->andReturn($label);

        return $el;
    }

    private function createMockOccurrence(): Mockery\MockInterface
    {
        return Mockery::mock(EventOccurrenceDomainObject::class, [
            'getStartDate' => '2024-07-20 14:00:00',
            'getEndDate' => '2024-07-20 18:00:00',
            'getLabel' => 'Afternoon Show',
            'getEventLocation' => null,
        ]);
    }

    private function createMockOccurrenceWithLocation(?EventLocationDomainObject $eventLocation): Mockery\MockInterface
    {
        return Mockery::mock(EventOccurrenceDomainObject::class, [
            'getStartDate' => '2024-07-20 14:00:00',
            'getEndDate' => '2024-07-20 18:00:00',
            'getLabel' => 'Afternoon Show',
            'getEventLocation' => $eventLocation,
        ]);
    }

    private function createMockOrder(): OrderDomainObject
    {
        $orderItem = Mockery::mock(OrderItemDomainObject::class, [
            'getProductPriceId' => 123,
            'getPrice' => 4999,
            'getItemName' => 'General Admission',
        ]);

        $orderItems = new Collection([$orderItem]);

        return Mockery::mock(OrderDomainObject::class, [
            'getPublicId' => 'ORD-123456',
            'getTotalGross' => 9999,
            'getFirstName' => 'John',
            'getLastName' => 'Doe',
            'getEmail' => 'john@example.com',
            'getCreatedAt' => '2024-01-15 10:30:00',
            'getShortId' => 'ABC123',
            'isOrderAwaitingOfflinePayment' => false,
            'getPaymentProvider' => PaymentProviders::STRIPE->value,
            'getOrderItems' => $orderItems,
            'getCurrency' => 'USD',
            'getLocale' => 'en',
        ]);
    }

    private function createMockEvent(): EventDomainObject
    {
        return Mockery::mock(EventDomainObject::class, [
            'getTitle' => 'Amazing Event',
            'getDescription' => 'This is an amazing event',
            'getStartDate' => '2024-02-15 19:00:00',
            'getEndDate' => '2024-02-15 22:00:00',
            'getTimezone' => 'America/New_York',
            'getCurrency' => 'USD',
            'getId' => 1,
            'getEventLocation' => null,
        ]);
    }

    private function createMockEventWithLocation(?EventLocationDomainObject $eventLocation): EventDomainObject
    {
        return Mockery::mock(EventDomainObject::class, [
            'getTitle' => 'Amazing Event',
            'getDescription' => 'This is an amazing event',
            'getStartDate' => '2024-02-15 19:00:00',
            'getEndDate' => '2024-02-15 22:00:00',
            'getTimezone' => 'America/New_York',
            'getCurrency' => 'USD',
            'getId' => 1,
            'getEventLocation' => $eventLocation,
        ]);
    }

    private function createMockOrganizer(): OrganizerDomainObject
    {
        return Mockery::mock(OrganizerDomainObject::class, [
            'getName' => 'Great Organizer',
            'getEmail' => 'contact@organizer.com',
        ]);
    }

    private function createMockEventSettings(): EventSettingDomainObject
    {
        return Mockery::mock(EventSettingDomainObject::class, [
            'getSupportEmail' => 'support@event.com',
            'getOfflinePaymentInstructions' => 'Pay by bank transfer',
            'getPostCheckoutMessage' => 'Thank you for your purchase!',
        ]);
    }

    private function createMockAttendee(): AttendeeDomainObject
    {
        return Mockery::mock(AttendeeDomainObject::class, [
            'getFirstName' => 'Jane',
            'getLastName' => 'Smith',
            'getEmail' => 'jane@example.com',
            'getProductPriceId' => 123,
            'getShortId' => 'ATT123',
        ]);
    }
}
