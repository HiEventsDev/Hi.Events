<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Mail\Organizer\OrderSummaryForOrganizer;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Email\TransactionalEmailTrackingService;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailer;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SendOrderDetailsServiceTest extends TestCase
{
    private SendOrderDetailsService $service;
    private MockInterface|EventRepositoryInterface $eventRepository;
    private MockInterface|OrderRepositoryInterface $orderRepository;
    private MockInterface|Mailer $mailer;
    private MockInterface|SendAttendeeTicketService $sendAttendeeTicketService;
    private MockInterface|MailBuilderService $mailBuilderService;
    private MockInterface|TransactionalEmailTrackingService $trackingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->mailer = Mockery::mock(Mailer::class);
        $this->sendAttendeeTicketService = Mockery::mock(SendAttendeeTicketService::class);
        $this->mailBuilderService = Mockery::mock(MailBuilderService::class);
        $this->trackingService = Mockery::mock(TransactionalEmailTrackingService::class);

        $this->service = new SendOrderDetailsService(
            $this->eventRepository,
            $this->orderRepository,
            $this->mailer,
            $this->sendAttendeeTicketService,
            $this->mailBuilderService,
            $this->trackingService,
        );
    }

    public function testOrganizerNotificationUsesOrganizerOrderSummaryEmailType(): void
    {
        $orderId = 42;
        $eventId = 7;
        $accountId = 3;
        $organizerEmail = 'organizer@example.com';

        [$orderStub] = $this->arrangeHappyPath(
            orderId: $orderId,
            eventId: $eventId,
            accountId: $accountId,
            organizerEmail: $organizerEmail,
            notifyOrganizer: true,
        );

        $allCallArgs = [];
        $this->trackingService->shouldReceive('recordAndSend')
            ->twice()
            ->withArgs(function (...$args) use (&$allCallArgs) {
                $allCallArgs[] = $args;
                return true;
            })
            ->andReturnNull();

        $this->service->sendOrderSummaryAndTicketEmails($orderStub);

        $organizerCall = null;
        foreach ($allCallArgs as $args) {
            foreach ($args as $arg) {
                if ($arg instanceof TransactionalEmailType && $arg === TransactionalEmailType::ORGANIZER_ORDER_SUMMARY) {
                    $organizerCall = $args;
                    break 2;
                }
            }
        }

        $this->assertNotNull($organizerCall, 'Expected recordAndSend to be called with ORGANIZER_ORDER_SUMMARY.');

        $hasOrganizerMail = false;
        $hasOrganizerRecipient = false;
        foreach ($organizerCall as $arg) {
            if ($arg === $organizerEmail) {
                $hasOrganizerRecipient = true;
            }
            if ($arg instanceof OrderSummaryForOrganizer) {
                $hasOrganizerMail = true;
            }
        }

        $this->assertTrue($hasOrganizerRecipient, 'Organizer call should use the organizer email as recipient.');
        $this->assertTrue($hasOrganizerMail, 'Organizer call should pass OrderSummaryForOrganizer mailable.');
    }

    public function testOrganizerNotificationSkippedWhenNotifyDisabled(): void
    {
        $orderId = 42;
        $eventId = 7;
        $accountId = 3;

        [$orderStub] = $this->arrangeHappyPath(
            orderId: $orderId,
            eventId: $eventId,
            accountId: $accountId,
            organizerEmail: 'organizer@example.com',
            notifyOrganizer: false,
        );

        $seenTypes = [];
        $this->trackingService->shouldReceive('recordAndSend')
            ->withArgs(function (...$args) use (&$seenTypes) {
                foreach ($args as $arg) {
                    if ($arg instanceof TransactionalEmailType) {
                        $seenTypes[] = $arg;
                    }
                }
                return true;
            })
            ->andReturnNull();

        $this->service->sendOrderSummaryAndTicketEmails($orderStub);

        $this->assertNotContains(
            TransactionalEmailType::ORGANIZER_ORDER_SUMMARY,
            $seenTypes,
            'Organizer notification must not be dispatched when notifyOrganizerOfNewOrders is false.'
        );
    }

    /**
     * @return array{0: OrderDomainObject} Returns the hydrated order mock.
     */
    private function arrangeHappyPath(
        int $orderId,
        int $eventId,
        int $accountId,
        string $organizerEmail,
        bool $notifyOrganizer,
    ): array {
        $organizer = Mockery::mock(OrganizerDomainObject::class);
        $organizer->shouldReceive('getEmail')->andReturn($organizerEmail);

        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $eventSettings->shouldReceive('getNotifyOrganizerOfNewOrders')->andReturn($notifyOrganizer);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn($eventId);
        $event->shouldReceive('getAccountId')->andReturn($accountId);
        $event->shouldReceive('getOrganizer')->andReturn($organizer);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getTitle')->andReturn('Test Event');
        $event->shouldReceive('getCurrency')->andReturn('USD');

        $order = Mockery::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getEventId')->andReturn($eventId);
        $order->shouldReceive('isOrderCompleted')->andReturn(true);
        $order->shouldReceive('isOrderAwaitingOfflinePayment')->andReturn(false);
        $order->shouldReceive('isOrderFailed')->andReturn(false);
        $order->shouldReceive('getIsManuallyCreated')->andReturn(false);
        $order->shouldReceive('getAttendees')->andReturn(collect());
        $order->shouldReceive('getLatestInvoice')->andReturn(null);
        $order->shouldReceive('getEmail')->andReturn('buyer@example.com');
        $order->shouldReceive('getLocale')->andReturn('en');
        $order->shouldReceive('getTotalGross')->andReturn(0);

        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findById')->with($orderId)->andReturn($order);

        $this->eventRepository->shouldReceive('loadRelation')
            ->with(Mockery::type(Relationship::class))
            ->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->with($eventId)->andReturn($event);

        $customerMail = Mockery::mock(OrderSummary::class);
        $customerMail->shouldReceive('envelope')->andReturn(new Envelope(subject: 'Your Order'));
        $this->mailBuilderService->shouldReceive('buildOrderSummaryMail')->andReturn($customerMail);

        return [$order];
    }
}
