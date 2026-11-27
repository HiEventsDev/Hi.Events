<?php

namespace Tests\Unit\Services\Domain\TicketTransfer;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TicketTransferBlock;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\TicketTransferStatus;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferEligibilityService;
use Tests\TestCase;

class TicketTransferEligibilityServiceTest extends TestCase
{
    private const NOW = '2026-11-25 12:00:00';

    private TicketTransferEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
        $this->service = new TicketTransferEligibilityService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_active_ticket_for_a_future_date_of_a_live_event_can_be_transferred(): void
    {
        $this->assertNull($this->service->blockOnStarting($this->context()));
    }

    public function test_each_rule_blocks_a_transfer(): void
    {
        $cases = [
            [TicketTransferBlock::TRANSFERS_DISABLED, $this->context(allowTransfer: false)],
            [TicketTransferBlock::TICKET_TYPE_NOT_TRANSFERABLE, $this->context(transferableType: false)],
            [TicketTransferBlock::EVENT_NOT_LIVE, $this->context(eventStatus: EventStatus::DRAFT->name)],
            [TicketTransferBlock::ORDER_NOT_COMPLETED, $this->context(orderStatus: OrderStatus::AWAITING_OFFLINE_PAYMENT->name)],
            [TicketTransferBlock::TICKET_NOT_ACTIVE, $this->context(attendeeStatus: AttendeeStatus::CANCELLED->name)],
            [TicketTransferBlock::CHECKED_IN, $this->context(checkedIn: true)],
            [TicketTransferBlock::DATE_CANCELLED, $this->context(occurrenceStatus: EventOccurrenceStatus::CANCELLED->name)],
            [TicketTransferBlock::DATE_PASSED, $this->context(startsAt: '2026-11-25 11:00:00')],
            [TicketTransferBlock::CUTOFF_PASSED, $this->context(startsAt: '2026-11-25 12:30:00', cutoffMinutes: 60)],
            [TicketTransferBlock::LIMIT_REACHED, $this->context(maxTransfers: 1, transfers: [$this->transfer(TicketTransferStatus::ACCEPTED->name, createdAt: '2026-11-01 09:00:00')])],
            [TicketTransferBlock::TRANSFER_PENDING, $this->context(transfers: [$this->transfer(TicketTransferStatus::PENDING->name)])],
        ];

        foreach ($cases as [$expected, $context]) {
            $this->assertSame($expected, $this->service->blockOnStarting($context), $expected->name);
        }
    }

    public function test_a_ticket_listed_for_resale_can_be_neither_transferred_nor_accepted_by_a_recipient(): void
    {
        $listed = $this->context(hasOpenListing: true);

        $this->assertSame(TicketTransferBlock::LISTED_FOR_RESALE, $this->service->blockOnStarting($listed));
        $this->assertSame(TicketTransferBlock::LISTED_FOR_RESALE, $this->service->blockOnHolderChange($listed));
    }

    public function test_a_ticket_within_its_cutoff_and_limit_can_still_be_transferred(): void
    {
        $this->assertNull($this->service->blockOnStarting($this->context(startsAt: '2026-11-25 13:30:00', cutoffMinutes: 60)));
        $this->assertNull($this->service->blockOnStarting($this->context(
            maxTransfers: 2,
            transfers: [$this->transfer(TicketTransferStatus::ACCEPTED->name, createdAt: '2026-11-01 09:00:00')],
        )));
    }

    public function test_an_expired_pending_transfer_does_not_block_a_new_one(): void
    {
        $expired = $this->transfer(TicketTransferStatus::PENDING->name, expiresAt: '2026-11-25 11:59:00', createdAt: '2026-11-20 09:00:00');

        $this->assertNull($this->service->blockOnStarting($this->context(transfers: [$expired])));
    }

    public function test_a_sixth_transfer_started_within_a_day_is_refused(): void
    {
        $recent = fn () => $this->transfer(TicketTransferStatus::CANCELLED->name, createdAt: '2026-11-25 09:00:00');
        $older = $this->transfer(TicketTransferStatus::CANCELLED->name, createdAt: '2026-11-23 09:00:00');

        $this->assertNull($this->service->blockOnStarting($this->context(transfers: [$recent(), $recent(), $recent(), $recent(), $older])));
        $this->assertSame(
            TicketTransferBlock::TOO_MANY_ATTEMPTS,
            $this->service->blockOnStarting($this->context(transfers: [$recent(), $recent(), $recent(), $recent(), $recent()])),
        );
    }

    public function test_accepting_ignores_the_pending_transfer_and_the_daily_attempt_limit(): void
    {
        $recent = fn (string $status) => $this->transfer($status, createdAt: '2026-11-25 09:00:00');
        $context = $this->context(transfers: [
            $recent(TicketTransferStatus::PENDING->name),
            $recent(TicketTransferStatus::CANCELLED->name),
            $recent(TicketTransferStatus::CANCELLED->name),
            $recent(TicketTransferStatus::CANCELLED->name),
            $recent(TicketTransferStatus::CANCELLED->name),
        ]);

        $this->assertNull($this->service->blockOnHolderChange($context));
    }

    public function test_a_new_transfer_expires_after_three_days_or_at_the_cutoff_whichever_is_sooner(): void
    {
        $farAway = $this->context(startsAt: '2026-12-25 20:00:00');
        $soon = $this->context(startsAt: '2026-11-26 20:00:00', cutoffMinutes: 120);

        $this->assertSame('2026-11-28 12:00:00', $this->service->expiryForNewTransfer($farAway)->toDateTimeString());
        $this->assertSame('2026-11-26 18:00:00', $this->service->expiryForNewTransfer($soon)->toDateTimeString());
    }

    private function context(
        bool $allowTransfer = true,
        bool $transferableType = true,
        string $eventStatus = 'LIVE',
        string $orderStatus = 'COMPLETED',
        string $attendeeStatus = 'ACTIVE',
        bool $checkedIn = false,
        string $occurrenceStatus = 'ACTIVE',
        string $startsAt = '2026-12-25 20:00:00',
        int $cutoffMinutes = 0,
        ?int $maxTransfers = null,
        array $transfers = [],
        bool $hasOpenListing = false,
    ): TicketTransferContextDTO {
        $settings = (new EventSettingDomainObject)
            ->setAllowTicketTransfer($allowTransfer)
            ->setTicketTransferCutoffMinutes($cutoffMinutes)
            ->setMaxTransfersPerTicket($maxTransfers);

        return new TicketTransferContextDTO(
            event: (new EventDomainObject)->setStatus($eventStatus)->setEventSettings($settings),
            order: (new OrderDomainObject)->setStatus($orderStatus),
            attendee: (new AttendeeDomainObject)->setStatus($attendeeStatus),
            product: (new ProductDomainObject)->setIsTransferable($transferableType),
            occurrence: (new EventOccurrenceDomainObject)->setStatus($occurrenceStatus)->setStartDate($startsAt),
            transfers: collect($transfers),
            is_checked_in: $checkedIn,
            has_open_listing: $hasOpenListing,
        );
    }

    private function transfer(string $status, string $expiresAt = '2026-11-27 12:00:00', string $createdAt = '2026-11-24 09:00:00'): TicketTransferDomainObject
    {
        return (new TicketTransferDomainObject)
            ->setStatus($status)
            ->setExpiresAt($expiresAt)
            ->setCreatedAt($createdAt);
    }
}
