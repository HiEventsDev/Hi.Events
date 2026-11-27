<?php

namespace Tests\Feature\Mail;

use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\TicketResale\TicketResaleNoticeMail;
use Tests\TestCase;

class TicketResaleMailTest extends TestCase
{
    private const ORDER_URL = 'https://example.test/order/abc123';

    public function test_each_notice_names_the_event_and_ticket_under_its_own_subject(): void
    {
        foreach (TicketResaleNotice::cases() as $notice) {
            $mail = $this->mail($notice, '£42.50');

            $mail->assertHasSubject($notice->subject());
            $mail->assertSeeInHtml('Winter Ball');
            $mail->assertSeeInHtml('General Admission');
        }
    }

    public function test_notices_that_carry_an_amount_show_it(): void
    {
        $withAmount = [
            TicketResaleNotice::LISTED_FOR_SELLER,
            TicketResaleNotice::SOLD_FOR_SELLER,
            TicketResaleNotice::REFUND_FAILED_FOR_SELLER,
            TicketResaleNotice::REFUND_FAILED_FOR_ORGANIZER,
        ];

        foreach ($withAmount as $notice) {
            $this->mail($notice, '£42.50')->assertSeeInHtml('£42.50');
        }
    }

    public function test_the_unsold_and_closed_notices_render_without_an_amount(): void
    {
        foreach ([TicketResaleNotice::UNSOLD_FOR_SELLER, TicketResaleNotice::CLOSED_FOR_SELLER] as $notice) {
            $mail = $this->mail($notice);

            $mail->assertHasSubject($notice->subject());
            $mail->assertSeeInHtml('Winter Ball');
            $mail->assertSeeInHtml('General Admission');
            $mail->assertDontSeeInHtml('£');
        }
    }

    public function test_the_button_appears_only_when_a_url_and_label_are_given(): void
    {
        $notice = TicketResaleNotice::UNSOLD_FOR_SELLER;

        $both = $this->mail($notice, null, self::ORDER_URL, 'View your ticket');
        $both->assertSeeInHtml(self::ORDER_URL);
        $both->assertSeeInHtml('View your ticket');

        $this->mail($notice, null, self::ORDER_URL, null)->assertDontSeeInHtml(self::ORDER_URL);
        $this->mail($notice, null, null, 'View your ticket')->assertDontSeeInHtml('View your ticket');
        $this->mail($notice)->assertDontSeeInHtml(self::ORDER_URL);
    }

    public function test_only_the_organizer_notice_is_not_a_buyer_mail(): void
    {
        foreach (TicketResaleNotice::cases() as $notice) {
            $this->assertSame(
                $notice !== TicketResaleNotice::REFUND_FAILED_FOR_ORGANIZER,
                $this->mail($notice, '£42.50')->isSentToBuyers(),
            );
        }
    }

    public function test_sellers_see_the_support_contact_and_the_organizer_does_not(): void
    {
        $this->mail(TicketResaleNotice::SOLD_FOR_SELLER, '£42.50')->assertSeeInHtml('help@example.test');
        $this->mail(TicketResaleNotice::REFUND_FAILED_FOR_ORGANIZER, '£42.50')->assertDontSeeInHtml('help@example.test');
    }

    public function test_no_notice_greets_anyone_by_name(): void
    {
        foreach (TicketResaleNotice::cases() as $notice) {
            $mail = $this->mail($notice, '£42.50');

            $mail->assertDontSeeInHtml('Hello');
            $mail->assertDontSeeInHtml('Dear');
        }
    }

    private function mail(
        TicketResaleNotice $notice,
        ?string $amount = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): TicketResaleNoticeMail {
        return new TicketResaleNoticeMail(
            notice: $notice,
            event: $this->event(),
            organizer: $this->organizer(),
            eventSettings: $this->settings(),
            ticketTitle: 'General Admission',
            formattedAmount: $amount,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
        );
    }

    private function event(): EventDomainObject
    {
        return (new EventDomainObject)->setId(1)->setTitle('Winter Ball')->setTimezone('UTC')->setStatus('LIVE');
    }

    private function organizer(): OrganizerDomainObject
    {
        return (new OrganizerDomainObject)->setId(1)->setName('Ball Committee');
    }

    private function settings(): EventSettingDomainObject
    {
        return (new EventSettingDomainObject)->setSupportEmail('help@example.test');
    }
}
