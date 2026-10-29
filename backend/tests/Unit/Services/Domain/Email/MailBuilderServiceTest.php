<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use HiEvents\Services\Domain\Email\EmailTokenContextBuilder;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Lineup\EmailLineupHighlightsService;
use HiEvents\Services\Domain\Order\OfflinePaymentInstructionsRenderService;
use Mockery as m;
use Tests\TestCase;

class MailBuilderServiceTest extends TestCase
{
    private EmailLineupHighlightsService $lineupHighlights;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lineupHighlights = m::mock(EmailLineupHighlightsService::class)->makePartial();
        $this->lineupHighlights->shouldReceive('build')->andReturnNull();
        $this->app->instance(EmailLineupHighlightsService::class, $this->lineupHighlights);
    }

    public function test_lineup_highlights_are_built_in_the_buyers_language(): void
    {
        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')->andReturn(null);
        $builtIn = null;
        $highlights = m::mock(EmailLineupHighlightsService::class);
        $highlights->shouldReceive('build')->andReturnUsing(function () use (&$builtIn) {
            $builtIn = app()->getLocale();

            return null;
        });

        $service = new MailBuilderService(
            $emailTemplateService,
            app(EmailTokenContextBuilder::class),
            app(OfflinePaymentInstructionsRenderService::class),
            $highlights,
        );

        $organizer = (new OrganizerDomainObject)->setId(1)->setName('Organizer')->setEmail('organizer@example.com');
        $settings = (new EventSettingDomainObject)->setId(20)->setEventId(10)->setSupportEmail('support@example.com');
        $event = (new EventDomainObject)->setId(10)->setAccountId(1)->setTitle('Fest')->setCurrency('EUR')->setTimezone('UTC')
            ->setOrganizer($organizer)->setEventSettings($settings);
        $order = (new OrderDomainObject)->setId(30)->setEventId(10)->setShortId('o')->setPublicId('ORD-1')->setFirstName('Jo')
            ->setLastName('Buyer')->setEmail('buyer@example.com')->setTotalGross(0)->setCurrency('EUR')->setLocale('de')
            ->setCreatedAt('2026-08-01 12:00:00')->setStatus(OrderStatus::COMPLETED->name)->setPaymentStatus(OrderPaymentStatus::NO_PAYMENT_REQUIRED->name);

        $service->buildOrderSummaryMail($order, $event, $settings, $organizer);

        $this->assertSame('de', $builtIn);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_order_summary_blade_fallback_receives_rendered_instructions(): void
    {
        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')->andReturn(null);

        $service = new MailBuilderService(
            $emailTemplateService,
            app(EmailTokenContextBuilder::class),
            app(OfflinePaymentInstructionsRenderService::class),
            $this->lineupHighlights,
        );

        $organizer = (new OrganizerDomainObject)
            ->setId(1)
            ->setName('Example Organizer')
            ->setEmail('organizer@example.com');

        $settings = (new EventSettingDomainObject)
            ->setId(20)
            ->setEventId(10)
            ->setSupportEmail('support@example.com')
            ->setOfflinePaymentInstructions('<p>Use {{ order.number }} as your reference</p>');

        $event = (new EventDomainObject)
            ->setId(10)
            ->setAccountId(1)
            ->setTitle('Summer Session')
            ->setCurrency('GBP')
            ->setTimezone('UTC')
            ->setOrganizer($organizer)
            ->setEventSettings($settings);

        $order = (new OrderDomainObject)
            ->setId(30)
            ->setEventId(10)
            ->setShortId('order-short-id')
            ->setPublicId('ORD-12345')
            ->setFirstName('Jane')
            ->setLastName('Buyer')
            ->setEmail('buyer@example.com')
            ->setTotalGross(125.50)
            ->setCurrency('GBP')
            ->setCreatedAt('2026-08-01 12:00:00')
            ->setStatus(OrderStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentProvider(PaymentProviders::OFFLINE->value);

        $service->buildOrderSummaryMail($order, $event, $settings, $organizer);

        $this->assertSame(
            '<p>Use ORD-12345 as your reference</p>',
            $settings->getOfflinePaymentInstructions(),
        );
    }

    public function test_attendees_sharing_an_email_get_one_mail_with_every_ticket_sorted_by_date(): void
    {
        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')->once()->andReturn(null);

        [$order, $event, $settings, $organizer] = $this->ticketFixtures();
        $later = $this->attendee(1, (new EventOccurrenceDomainObject)->setId(2)->setStartDate('2026-11-12 18:00:00'));
        $earlier = $this->attendee(2, (new EventOccurrenceDomainObject)->setId(1)->setStartDate('2026-11-05 18:00:00'));

        $mails = $this->builder($emailTemplateService)
            ->buildAttendeeTicketMails(collect([$later, $earlier]), $order, $event, $settings, $organizer);

        $this->assertCount(1, $mails);
        $tickets = $mails[0]->content()->with['tickets'];
        $this->assertCount(2, $tickets);
        $this->assertSame($earlier, $tickets[0]['attendee']);
        $this->assertSame($later, $tickets[1]['attendee']);
    }

    public function test_custom_ticket_template_sends_one_mail_per_attendee(): void
    {
        $template = (new EmailTemplateDomainObject)
            ->setSubject('Ticket for {{ attendee.name }}')
            ->setBody('<p>{{ ticket.url }}</p>')
            ->setCta(['label' => 'View Ticket', 'url_token' => 'ticket.url']);

        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')->once()->andReturn($template);
        $emailTemplateService->shouldReceive('renderTemplate')
            ->twice()
            ->andReturnUsing(fn ($t, array $context) => new RenderedEmailTemplateDTO(
                subject: 'Ticket for '.$context['attendee']['name'],
                body: $context['ticket']['url'],
                cta: null,
            ));

        [$order, $event, $settings, $organizer] = $this->ticketFixtures();
        $first = $this->attendee(1, (new EventOccurrenceDomainObject)->setId(1)->setStartDate('2026-11-05 18:00:00'), 'Ann');
        $second = $this->attendee(2, (new EventOccurrenceDomainObject)->setId(2)->setStartDate('2026-11-12 18:00:00'), 'Bob');

        $mails = $this->builder($emailTemplateService)
            ->buildAttendeeTicketMails(collect([$first, $second]), $order, $event, $settings, $organizer);

        $this->assertCount(2, $mails);
        $this->assertSame('Ticket for Ann Buyer', $mails[0]->envelope()->subject);
        $this->assertSame('Ticket for Bob Buyer', $mails[1]->envelope()->subject);
    }

    public function test_single_attendee_gets_a_single_ticket_mail(): void
    {
        $emailTemplateService = m::mock(EmailTemplateService::class);
        $emailTemplateService->shouldReceive('getTemplateByType')->once()->andReturn(null);

        [$order, $event, $settings, $organizer] = $this->ticketFixtures();
        $attendee = $this->attendee(1, (new EventOccurrenceDomainObject)->setId(1)->setStartDate('2026-11-05 18:00:00'));

        $mails = $this->builder($emailTemplateService)
            ->buildAttendeeTicketMails(collect([$attendee]), $order, $event, $settings, $organizer);

        $this->assertCount(1, $mails);
        $this->assertSame('🎟️ Your Ticket for Summer Session', $mails[0]->envelope()->subject);
    }

    private function builder(EmailTemplateService $emailTemplateService): MailBuilderService
    {
        return new MailBuilderService(
            $emailTemplateService,
            app(EmailTokenContextBuilder::class),
            app(OfflinePaymentInstructionsRenderService::class),
            $this->lineupHighlights,
        );
    }

    private function ticketFixtures(): array
    {
        $organizer = (new OrganizerDomainObject)->setId(1)->setName('Organizer')->setEmail('organizer@example.com');
        $settings = (new EventSettingDomainObject)->setSupportEmail('support@example.com');
        $event = (new EventDomainObject)
            ->setId(10)
            ->setAccountId(1)
            ->setTitle('Summer Session')
            ->setCurrency('GBP')
            ->setTimezone('UTC');
        $order = (new OrderDomainObject)
            ->setId(30)
            ->setShortId('o_short')
            ->setPublicId('ORD-1')
            ->setTotalGross(0)
            ->setCurrency('GBP')
            ->setLocale('en')
            ->setPaymentProvider(PaymentProviders::STRIPE->value)
            ->setCreatedAt('2026-08-01 12:00:00')
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setOrderItems(collect());

        return [$order, $event, $settings, $organizer];
    }

    private function attendee(int $id, EventOccurrenceDomainObject $occurrence, string $firstName = 'Sam'): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setId($id)
            ->setShortId('a_'.$id)
            ->setFirstName($firstName)
            ->setLastName('Buyer')
            ->setEmail('buyer@example.com')
            ->setEventOccurrenceId($occurrence->getId())
            ->setEventOccurrence($occurrence);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
