<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use HiEvents\Services\Domain\Mail\TeamOrderNotificationRecipientService;
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

    public function test_order_notifications_go_to_the_organizer_and_opted_in_team_members(): void
    {
        $order = $this->completedOrder();
        $recipients = [];

        $teamRecipients = m::mock(TeamOrderNotificationRecipientService::class);
        $teamRecipients->shouldReceive('recipientsFor')
            ->once()
            ->with(m::type(EventDomainObject::class), 'organizer@example.com')
            ->andReturn([$this->teamMember('team@example.com', 'en')]);

        $this->service($order, notifyOrganizer: true, teamRecipients: $teamRecipients, recipients: $recipients)
            ->sendOrderSummaryAndTicketEmails($order);

        $this->assertContains('organizer@example.com', $recipients);
        $this->assertContains('team@example.com', $recipients);
    }

    public function test_team_members_are_notified_even_when_the_organizer_email_is_off(): void
    {
        $order = $this->completedOrder();
        $recipients = [];

        $teamRecipients = m::mock(TeamOrderNotificationRecipientService::class);
        $teamRecipients->shouldReceive('recipientsFor')->once()->with(m::any(), null)->andReturn([$this->teamMember('team@example.com', 'en')]);

        $this->service($order, notifyOrganizer: false, teamRecipients: $teamRecipients, recipients: $recipients)
            ->sendOrderSummaryAndTicketEmails($order);

        $this->assertNotContains('organizer@example.com', $recipients);
        $this->assertContains('team@example.com', $recipients);
    }

    public function test_team_notifications_are_sent_in_each_members_locale(): void
    {
        $order = $this->completedOrder()->setLocale('de');
        $recipients = [];
        $locales = [];

        $teamRecipients = m::mock(TeamOrderNotificationRecipientService::class);
        $teamRecipients->shouldReceive('recipientsFor')->once()->andReturn([
            $this->teamMember('marie@example.com', 'fr'),
            $this->teamMember('sam@example.com', 'en'),
        ]);

        $this->service($order, notifyOrganizer: false, teamRecipients: $teamRecipients, recipients: $recipients, locales: $locales)
            ->sendOrderSummaryAndTicketEmails($order);

        $this->assertSame('fr', $locales['marie@example.com']);
        $this->assertSame('en', $locales['sam@example.com']);
        $this->assertSame('de', $locales['buyer@example.com']);
    }

    public function test_manual_orders_notify_nobody_on_the_team(): void
    {
        $order = $this->completedOrder()->setIsManuallyCreated(true);
        $recipients = [];

        $teamRecipients = m::mock(TeamOrderNotificationRecipientService::class);
        $teamRecipients->shouldNotReceive('recipientsFor');

        $this->service($order, notifyOrganizer: true, teamRecipients: $teamRecipients, recipients: $recipients)
            ->sendOrderSummaryAndTicketEmails($order);

        $this->assertNotContains('organizer@example.com', $recipients);
    }

    private function completedOrder(): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(31)
            ->setEventId(10)
            ->setEmail('buyer@example.com')
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setOrderItems(collect())
            ->setAttendees(collect());
    }

    private function service(
        OrderDomainObject $order,
        bool $notifyOrganizer = false,
        ?TeamOrderNotificationRecipientService $teamRecipients = null,
        array &$recipients = [],
        array &$locales = [],
    ): SendOrderDetailsService {
        $organizer = (new OrganizerDomainObject)->setId(1)->setEmail('organizer@example.com');
        $event = (new EventDomainObject)
            ->setId(10)
            ->setOrganizer($organizer)
            ->setEventSettings((new EventSettingDomainObject)->setNotifyOrganizerOfNewOrders($notifyOrganizer));

        if ($teamRecipients === null) {
            $teamRecipients = m::mock(TeamOrderNotificationRecipientService::class);
            $teamRecipients->shouldReceive('recipientsFor')->andReturn([]);
        }

        $orderRepository = m::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $orderRepository->shouldReceive('findById')->andReturn($order);

        $eventRepository = m::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->andReturn($event);

        $mailer = m::mock(Mailer::class);
        $mailer->shouldReceive('to')->andReturnUsing(function ($address) use (&$recipients, &$mailer) {
            $recipients[] = $address;

            return $mailer;
        });
        $mailer->shouldReceive('locale')->andReturnUsing(function ($locale) use (&$recipients, &$locales, &$mailer) {
            $locales[end($recipients)] = $locale;

            return $mailer;
        });
        $mailer->shouldReceive('send');

        $mailBuilderService = m::mock(MailBuilderService::class);
        $mailBuilderService->shouldReceive('buildOrderSummaryMail')->andReturn(m::mock(OrderSummary::class));

        return new SendOrderDetailsService(
            $eventRepository,
            $orderRepository,
            $mailer,
            $this->sendAttendeeTicketService,
            $mailBuilderService,
            $teamRecipients,
        );
    }

    private function teamMember(string $email, string $locale): UserDomainObject
    {
        return (new UserDomainObject)->setEmail($email)->setLocale($locale);
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
