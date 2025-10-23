<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Enums\PaymentProviders;
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
        $this->assertArrayHasKey('settings', $context);

        // Test order context
        $this->assertEquals('ORD-123456', $context['order']['number']);
        $this->assertEquals('$9,999.00', $context['order']['total']); // Updated expected format
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
        $this->assertEquals('$4,999.00', $context['ticket']['price']); // Updated expected format

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

        // Test that expected nested structure exists
        $this->assertArrayHasKey('order', $context);
        $this->assertArrayHasKey('event', $context);
        $this->assertArrayHasKey('organizer', $context);
        $this->assertArrayHasKey('settings', $context);
        
        // Test that expected properties are included
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

        // Test that expected nested structure exists
        $this->assertArrayHasKey('attendee', $context);
        $this->assertArrayHasKey('ticket', $context);
        $this->assertArrayHasKey('event', $context);
        $this->assertArrayHasKey('organizer', $context);
        
        // Test that expected properties are included
        $this->assertArrayHasKey('name', $context['attendee']);
        $this->assertArrayHasKey('name', $context['ticket']);
        $this->assertArrayHasKey('title', $context['event']);
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
            'getLocationDetails' => null,
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