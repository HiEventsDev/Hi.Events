<?php

namespace Tests\Feature\Mail;

use HiEvents\DomainObjects\Enums\TicketTransferNotice;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\TicketTransfer\TicketTransferInvitationMail;
use HiEvents\Mail\TicketTransfer\TicketTransferNoticeMail;
use Tests\TestCase;

class TicketTransferMailTest extends TestCase
{
    private const CLAIM_URL = 'https://example.test/transfer/tt_token';

    public function test_the_invitation_names_the_event_and_ticket_links_to_the_claim_page_and_greets_no_one(): void
    {
        $mail = new TicketTransferInvitationMail(
            event: $this->event(),
            organizer: $this->organizer(),
            eventSettings: $this->settings(),
            ticketTitle: 'General Admission',
            maskedSenderEmail: 'b•••@example.test',
            claimUrl: self::CLAIM_URL,
            expiresAtFormatted: 'Friday, November 27 · 6:00 PM',
        );

        $mail->assertHasSubject(__('You have been sent a ticket for :event', ['event' => 'Winter Ball']));
        $mail->assertSeeInHtml('General Admission');
        $mail->assertSeeInHtml('b•••@example.test');
        $mail->assertSeeInHtml(self::CLAIM_URL);
        $mail->assertSeeInHtml('Friday, November 27');
        $mail->assertDontSeeInHtml('Hello');
        $mail->assertDontSeeInHtml('Dear');
    }

    public function test_each_notice_says_what_happened_to_which_ticket_and_who_was_involved(): void
    {
        foreach (TicketTransferNotice::cases() as $notice) {
            $mail = new TicketTransferNoticeMail(
                notice: $notice,
                event: $this->event(),
                organizer: $this->organizer(),
                eventSettings: $this->settings(),
                ticketTitle: 'General Admission',
                maskedEmail: 'r•••@example.test',
            );

            $mail->assertHasSubject($notice->subject());
            $mail->assertSeeInHtml('Winter Ball');
            $mail->assertSeeInHtml('General Admission');
            $mail->assertSeeInHtml('r•••@example.test');
        }
    }

    public function test_only_a_completed_transfer_asks_the_reader_to_report_it_if_unexpected(): void
    {
        $rendered = fn (TicketTransferNotice $notice) => (new TicketTransferNoticeMail(
            notice: $notice,
            event: $this->event(),
            organizer: $this->organizer(),
            eventSettings: $this->settings(),
            ticketTitle: 'General Admission',
            maskedEmail: 'r•••@example.test',
        ))->render();

        $this->assertStringContainsString('help@example.test', $rendered(TicketTransferNotice::ACCEPTED_FOR_SENDER));
        $this->assertStringContainsString('help@example.test', $rendered(TicketTransferNotice::ACCEPTED_FOR_BUYER));
        $this->assertStringNotContainsString('help@example.test', $rendered(TicketTransferNotice::DECLINED_FOR_SENDER));
        $this->assertStringNotContainsString('help@example.test', $rendered(TicketTransferNotice::CANCELLED_FOR_RECIPIENT));
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
