<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Services\Domain\Email\EmailTokenContextBuilder;
use Tests\TestCase;
use Mockery;

class EmailTokenContextBuilderTest extends TestCase
{
    private EmailTokenContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextBuilder = new EmailTokenContextBuilder();
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
        $this->assertArrayHasKey('customer', $context);

        // Test order context
        $this->assertEquals('ORD-123456', $context['order']['order_code']);
        $this->assertEquals('$99.99', $context['order']['total_gross_formatted']);
        $this->assertEquals('confirmed', $context['order']['status']);

        // Test event context
        $this->assertEquals('Amazing Event', $context['event']['title']);
        $this->assertEquals('This is an amazing event', $context['event']['description']);

        // Test organizer context
        $this->assertEquals('Great Organizer', $context['organizer']['name']);
        $this->assertEquals('contact@organizer.com', $context['organizer']['email']);

        // Test customer context
        $this->assertEquals('John', $context['customer']['first_name']);
        $this->assertEquals('Doe', $context['customer']['last_name']);
        $this->assertEquals('john@example.com', $context['customer']['email']);
    }

    public function test_builds_attendee_ticket_context(): void
    {
        $ticket = $this->createMockTicket();
        $attendee = $this->createMockAttendee();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $context = $this->contextBuilder->buildAttendeeTicketContext(
            $ticket,
            $attendee,
            $event,
            $organizer,
            $eventSettings
        );

        $this->assertIsArray($context);
        $this->assertArrayHasKey('ticket', $context);
        $this->assertArrayHasKey('attendee', $context);
        $this->assertArrayHasKey('event', $context);
        $this->assertArrayHasKey('organizer', $context);

        // Test ticket context
        $this->assertEquals('TKT-789', $context['ticket']['reference_number']);
        $this->assertEquals('General Admission', $context['ticket']['title']);

        // Test attendee context
        $this->assertEquals('Jane', $context['attendee']['first_name']);
        $this->assertEquals('Smith', $context['attendee']['last_name']);
        $this->assertEquals('jane@example.com', $context['attendee']['email']);

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

        // Test that only whitelisted properties are included
        $this->assertArrayNotHasKey('password', $context['customer'] ?? []);
        $this->assertArrayNotHasKey('internal_notes', $context['order'] ?? []);
        
        // Test that expected whitelisted properties are included
        $this->assertArrayHasKey('order_code', $context['order']);
        $this->assertArrayHasKey('first_name', $context['customer']);
        $this->assertArrayHasKey('title', $context['event']);
    }

    public function test_whitelists_only_allowed_tokens_for_attendee_ticket(): void
    {
        $ticket = $this->createMockTicket();
        $attendee = $this->createMockAttendee();
        $event = $this->createMockEvent();
        $organizer = $this->createMockOrganizer();
        $eventSettings = $this->createMockEventSettings();

        $context = $this->contextBuilder->buildAttendeeTicketContext(
            $ticket,
            $attendee,
            $event,
            $organizer,
            $eventSettings
        );

        // Test that only whitelisted properties are included
        $this->assertArrayNotHasKey('password', $context['attendee'] ?? []);
        $this->assertArrayNotHasKey('internal_notes', $context['ticket'] ?? []);
        
        // Test that expected whitelisted properties are included
        $this->assertArrayHasKey('reference_number', $context['ticket']);
        $this->assertArrayHasKey('first_name', $context['attendee']);
        $this->assertArrayHasKey('title', $context['event']);
    }

    private function createMockOrder(): OrderDomainObject
    {
        return Mockery::mock(OrderDomainObject::class, [
            'getOrderCode' => 'ORD-123456',
            'getTotalGrossFormatted' => '$99.99',
            'getStatus' => 'confirmed',
            'getFirstName' => 'John',
            'getLastName' => 'Doe',
            'getEmail' => 'john@example.com',
            'getCreatedAt' => '2024-01-15 10:30:00',
            'getTotalGross' => 9999,
        ]);
    }

    private function createMockEvent(): EventDomainObject
    {
        return Mockery::mock(EventDomainObject::class, [
            'getTitle' => 'Amazing Event',
            'getDescription' => 'This is an amazing event',
            'getStartDate' => '2024-02-15',
            'getEndDate' => '2024-02-16',
            'getTimezone' => 'America/New_York',
            'getCurrency' => 'USD',
        ]);
    }

    private function createMockOrganizer(): OrganizerDomainObject
    {
        return Mockery::mock(OrganizerDomainObject::class, [
            'getName' => 'Great Organizer',
            'getEmail' => 'contact@organizer.com',
            'getWebsite' => 'https://organizer.com',
            'getPhone' => '+1-555-0123',
        ]);
    }

    private function createMockEventSettings(): EventSettingDomainObject
    {
        return Mockery::mock(EventSettingDomainObject::class, [
            'getSupportEmail' => 'support@event.com',
            'getEmailFooterMessage' => 'Thank you for your business!',
        ]);
    }

    private function createMockTicket(): TicketDomainObject
    {
        return Mockery::mock(TicketDomainObject::class, [
            'getReferenceNumber' => 'TKT-789',
            'getTitle' => 'General Admission',
            'getDescription' => 'General admission ticket',
            'getPrice' => 4999,
            'getPriceFormatted' => '$49.99',
        ]);
    }

    private function createMockAttendee(): AttendeeDomainObject
    {
        return Mockery::mock(AttendeeDomainObject::class, [
            'getFirstName' => 'Jane',
            'getLastName' => 'Smith',
            'getEmail' => 'jane@example.com',
            'getTicketReference' => 'TKT-789',
        ]);
    }
}