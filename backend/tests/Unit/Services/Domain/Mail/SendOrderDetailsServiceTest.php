<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Collection;
use Mockery as m;
use Mockery\MockInterface;
use Tests\TestCase;

class SendOrderDetailsServiceTest extends TestCase
{
    private SendAttendeeTicketService|MockInterface $sendAttendeeTicketService;

    public function test_groups_ticket_emails_by_address_and_skips_cancelled_and_emailless_attendees(): void
    {
        $order = (new OrderDomainObject)
            ->setId(30)
            ->setEventId(10)
            ->setEmail('buyer@example.com')
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setOrderItems(collect())
            ->setAttendees(collect([
                $this->attendee(1, 'buyer@example.com'),
                $this->attendee(2, 'friend@example.com'),
                $this->attendee(3, 'buyer@example.com'),
                $this->attendee(4, null),
                $this->attendee(5, 'buyer@example.com', AttendeeStatus::CANCELLED),
                $this->attendee(6, 'buyer@example.com', AttendeeStatus::AWAITING_PAYMENT),
            ]));

        $sentGroups = [];
        $this->sendAttendeeTicketService
            ->shouldReceive('sendTickets')
            ->twice()
            ->andReturnUsing(function (OrderDomainObject $order, Collection $attendees) use (&$sentGroups): void {
                $sentGroups[] = $attendees->map(fn (AttendeeDomainObject $a) => $a->getId())->all();
            });

        $this->service($order)->sendOrderSummaryAndTicketEmails($order);

        $this->assertSame([[1, 3, 6], [2]], $sentGroups);
    }

    private function service(OrderDomainObject $order): SendOrderDetailsService
    {
        $organizer = (new OrganizerDomainObject)->setId(1)->setEmail('organizer@example.com');
        $event = (new EventDomainObject)
            ->setId(10)
            ->setOrganizer($organizer)
            ->setEventSettings((new EventSettingDomainObject)->setNotifyOrganizerOfNewOrders(false));

        $orderRepository = m::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $orderRepository->shouldReceive('findById')->andReturn($order);

        $eventRepository = m::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->andReturn($event);

        $mailer = m::mock(Mailer::class);
        $mailer->shouldReceive('to')->andReturnSelf();
        $mailer->shouldReceive('locale')->andReturnSelf();
        $mailer->shouldReceive('send');

        $mailBuilderService = m::mock(MailBuilderService::class);
        $mailBuilderService->shouldReceive('buildOrderSummaryMail')->andReturn(m::mock(OrderSummary::class));

        return new SendOrderDetailsService(
            $eventRepository,
            $orderRepository,
            $mailer,
            $this->sendAttendeeTicketService,
            $mailBuilderService,
        );
    }

    private function attendee(int $id, ?string $email, AttendeeStatus $status = AttendeeStatus::ACTIVE): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setId($id)
            ->setEmail($email)
            ->setStatus($status->name);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sendAttendeeTicketService = m::mock(SendAttendeeTicketService::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
