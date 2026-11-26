<?php

namespace Tests\Unit\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Domain\SelfService\TicketControlService;
use Tests\TestCase;

class TicketControlServiceTest extends TestCase
{
    private const BUYER = 'buyer@example.test';

    private const GUEST = 'guest@example.test';

    public function test_the_person_named_on_a_ticket_controls_it(): void
    {
        $this->assertTrue($this->controlsTicket(self::GUEST, transferredAt: null));
        $this->assertTrue($this->controlsTicket(self::GUEST, transferredAt: '2026-11-25 10:00:00'));
    }

    public function test_the_buyer_controls_a_ticket_that_was_never_transferred(): void
    {
        $this->assertTrue($this->controlsTicket(self::BUYER, transferredAt: null));
    }

    public function test_the_buyer_loses_control_once_the_ticket_is_transferred(): void
    {
        $this->assertFalse($this->controlsTicket(self::BUYER, transferredAt: '2026-11-25 10:00:00'));
    }

    public function test_addresses_are_compared_without_regard_to_case_or_padding(): void
    {
        $this->assertTrue($this->controlsTicket(' Buyer@Example.TEST ', transferredAt: null));
    }

    public function test_an_unverified_or_unrelated_person_controls_nothing(): void
    {
        $this->assertFalse($this->controlsTicket(null, transferredAt: null));
        $this->assertFalse($this->controlsTicket('stranger@example.test', transferredAt: null));
        $this->assertFalse((new TicketControlService)->controlsOrder(null, $this->order()));
        $this->assertFalse((new TicketControlService)->controlsOrder(self::GUEST, $this->order()));
    }

    public function test_a_ticket_without_an_email_is_controlled_only_through_its_order(): void
    {
        $attendee = (new AttendeeDomainObject)->setEmail(null)->setTransferredAt(null);

        $this->assertTrue((new TicketControlService)->controlsTicket(self::BUYER, $attendee, $this->order()));
        $this->assertFalse((new TicketControlService)->controlsTicket(null, $attendee, $this->order()));
    }

    private function controlsTicket(?string $verifiedEmail, ?string $transferredAt): bool
    {
        $attendee = (new AttendeeDomainObject)->setEmail(self::GUEST)->setTransferredAt($transferredAt);

        return (new TicketControlService)->controlsTicket($verifiedEmail, $attendee, $this->order());
    }

    private function order(): OrderDomainObject
    {
        return (new OrderDomainObject)->setEmail(self::BUYER);
    }
}
