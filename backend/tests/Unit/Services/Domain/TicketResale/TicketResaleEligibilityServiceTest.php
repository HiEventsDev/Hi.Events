<?php

namespace Tests\Unit\Services\Domain\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\TicketResaleBlock;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleContextDTO;
use HiEvents\Services\Domain\TicketResale\TicketResaleEligibilityService;
use Tests\TestCase;

class TicketResaleEligibilityServiceTest extends TestCase
{
    private const NOW = '2026-11-25 12:00:00';

    private TicketResaleEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
        $this->service = new TicketResaleEligibilityService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_paid_ticket_bought_online_for_a_future_date_of_a_live_event_can_be_listed(): void
    {
        $this->assertNull($this->service->blockOnListing($this->context()));
    }

    public function test_a_ticket_already_listed_cannot_be_listed_again(): void
    {
        $this->assertBlocked(TicketResaleBlock::ALREADY_LISTED, $this->context(hasOpenListing: true));
    }

    public function test_a_ticket_with_a_pending_transfer_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::TRANSFER_PENDING, $this->context(hasPendingTransfer: true));
    }

    public function test_a_ticket_cannot_be_listed_when_the_event_has_resale_turned_off(): void
    {
        $this->assertBlocked(TicketResaleBlock::RESALE_DISABLED, $this->context(allowResale: false));
    }

    public function test_a_ticket_cannot_be_listed_when_its_ticket_type_is_not_resellable(): void
    {
        $this->assertBlocked(TicketResaleBlock::TICKET_TYPE_NOT_RESELLABLE, $this->context(resellableType: false));
    }

    public function test_a_ticket_cannot_be_listed_when_the_event_is_not_live(): void
    {
        $this->assertBlocked(TicketResaleBlock::EVENT_NOT_LIVE, $this->context(eventStatus: EventStatus::DRAFT->name));
    }

    public function test_free_donation_and_registration_tickets_cannot_be_listed(): void
    {
        foreach ([ProductPriceType::FREE, ProductPriceType::DONATION, ProductPriceType::REGISTRATION] as $priceType) {
            $this->assertBlocked(TicketResaleBlock::PRICE_TYPE_NOT_SUPPORTED, $this->context(priceType: $priceType->name));
        }
    }

    public function test_a_tiered_ticket_can_be_listed(): void
    {
        $this->assertNull($this->service->blockOnListing($this->context(priceType: ProductPriceType::TIERED->name)));
    }

    public function test_a_ticket_cannot_be_listed_when_its_ticket_type_is_no_longer_on_sale(): void
    {
        $this->assertBlocked(TicketResaleBlock::TICKET_TYPE_NOT_ON_SALE, $this->context(priceOnSale: false));
    }

    public function test_a_reserved_seating_ticket_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::SEATED, $this->context(seated: true));
    }

    public function test_a_ticket_cannot_be_listed_until_its_order_is_completed(): void
    {
        $this->assertBlocked(TicketResaleBlock::ORDER_NOT_COMPLETED, $this->context(orderStatus: OrderStatus::AWAITING_OFFLINE_PAYMENT->name));
    }

    public function test_a_cancelled_ticket_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::TICKET_NOT_ACTIVE, $this->context(attendeeStatus: AttendeeStatus::CANCELLED->name));
    }

    public function test_a_checked_in_ticket_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::CHECKED_IN, $this->context(checkedIn: true));
    }

    public function test_a_ticket_that_was_transferred_to_its_holder_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::TRANSFERRED, $this->context(transferredAt: '2026-11-20 09:00:00'));
    }

    public function test_a_ticket_for_a_cancelled_date_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::DATE_CANCELLED, $this->context(occurrenceStatus: EventOccurrenceStatus::CANCELLED->name));
    }

    public function test_a_ticket_for_a_date_that_has_started_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::DATE_PASSED, $this->context(startsAt: '2026-11-25 11:00:00'));
    }

    public function test_a_ticket_cannot_be_listed_after_the_resale_cutoff(): void
    {
        $this->assertBlocked(TicketResaleBlock::CUTOFF_PASSED, $this->context(startsAt: '2026-11-25 13:30:00', cutoffMinutes: 120));
    }

    public function test_a_ticket_that_was_not_paid_online_through_stripe_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::NOT_PAID_ONLINE, $this->context(paidOnlineThroughStripe: false));
    }

    public function test_a_ticket_paid_on_a_stripe_account_the_organizer_has_replaced_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::STRIPE_ACCOUNT_CHANGED, $this->context(paidOnCurrentStripeAccount: false));
    }

    public function test_a_ticket_cannot_be_listed_when_the_account_does_not_settle_in_the_order_currency(): void
    {
        $this->assertBlocked(TicketResaleBlock::CURRENCY_NOT_SUPPORTED, $this->context(settlesInOrderCurrency: false));
    }

    public function test_a_ticket_on_an_order_with_a_refund_that_is_not_a_resale_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::ORDER_REFUNDED, $this->context(hasOnlyResaleRefunds: false));
    }

    public function test_a_ticket_that_cost_nothing_or_costs_nothing_now_cannot_be_listed(): void
    {
        $this->assertBlocked(TicketResaleBlock::NOTHING_TO_REFUND, $this->context(sellerUnitGross: 0));
        $this->assertBlocked(TicketResaleBlock::NOTHING_TO_REFUND, $this->context(currentUnitGross: 0));
    }

    public function test_the_cutoff_is_the_start_time_less_the_events_resale_cutoff_minutes(): void
    {
        $context = $this->context(startsAt: '2026-12-25 20:00:00', cutoffMinutes: 120);

        $this->assertSame('2026-12-25 18:00:00', $this->service->cutoff($context)->toDateTimeString());
        $this->assertSame('2026-12-25 20:00:00', $this->service->cutoff($this->context(startsAt: '2026-12-25 20:00:00', cutoffMinutes: 0))->toDateTimeString());
    }

    public function test_a_ticket_can_be_listed_up_to_the_cutoff_and_not_after_it(): void
    {
        $atCutoff = $this->context(startsAt: '2026-11-25 14:00:00', cutoffMinutes: 120);
        $justAfterCutoff = $this->context(startsAt: '2026-11-25 13:59:59', cutoffMinutes: 120);

        $this->assertFalse($this->service->isPastCutoff($atCutoff));
        $this->assertNull($this->service->blockOnListing($atCutoff));
        $this->assertTrue($this->service->isPastCutoff($justAfterCutoff));
        $this->assertBlocked(TicketResaleBlock::CUTOFF_PASSED, $justAfterCutoff);
    }

    public function test_an_existing_listing_is_reported_before_every_other_problem(): void
    {
        $this->assertBlocked(TicketResaleBlock::ALREADY_LISTED, $this->context(
            allowResale: false,
            resellableType: false,
            eventStatus: EventStatus::DRAFT->name,
            priceType: ProductPriceType::FREE->name,
            priceOnSale: false,
            seated: true,
            orderStatus: OrderStatus::CANCELLED->name,
            attendeeStatus: AttendeeStatus::CANCELLED->name,
            checkedIn: true,
            transferredAt: '2026-11-20 09:00:00',
            occurrenceStatus: EventOccurrenceStatus::CANCELLED->name,
            startsAt: '2026-11-25 11:00:00',
            hasPendingTransfer: true,
            hasOpenListing: true,
            paidOnlineThroughStripe: false,
            paidOnCurrentStripeAccount: false,
            settlesInOrderCurrency: false,
            hasOnlyResaleRefunds: false,
            sellerUnitGross: 0,
            currentUnitGross: 0,
        ));
    }

    public function test_a_pending_transfer_is_reported_before_disabled_resale(): void
    {
        $this->assertBlocked(TicketResaleBlock::TRANSFER_PENDING, $this->context(allowResale: false, hasPendingTransfer: true));
    }

    public function test_event_rules_then_ticket_state_then_date_then_payment_then_amount_are_checked_in_that_order(): void
    {
        $this->assertBlocked(TicketResaleBlock::SEATED, $this->context(seated: true, checkedIn: true));
        $this->assertBlocked(TicketResaleBlock::TRANSFERRED, $this->context(transferredAt: '2026-11-20 09:00:00', startsAt: '2026-11-25 11:00:00'));
        $this->assertBlocked(TicketResaleBlock::CUTOFF_PASSED, $this->context(startsAt: '2026-11-25 13:30:00', paidOnlineThroughStripe: false));
        $this->assertBlocked(TicketResaleBlock::ORDER_REFUNDED, $this->context(hasOnlyResaleRefunds: false, sellerUnitGross: 0));
    }

    public function test_every_block_has_its_own_message_for_the_ticket_buyer(): void
    {
        $messages = array_map(fn (TicketResaleBlock $block) => $block->message(), TicketResaleBlock::cases());

        $this->assertNotContains('', $messages);
        $this->assertSame($messages, array_unique($messages));
    }

    private function assertBlocked(TicketResaleBlock $expected, TicketResaleContextDTO $context): void
    {
        $this->assertSame($expected, $this->service->blockOnListing($context));
    }

    public function test_a_ticket_whose_payment_fees_are_not_recorded_yet_is_offered_but_cannot_be_listed_until_they_are(): void
    {
        $context = $this->context(paymentFeesRecorded: false, settlesInOrderCurrency: false);
        $service = new TicketResaleEligibilityService;

        $this->assertNull($service->blockOnOfferingResale($context));
        $this->assertSame(TicketResaleBlock::PAYMENT_NOT_CONFIRMED, $service->blockOnListing($context));
    }

    public function test_a_ticket_with_fees_recorded_in_another_currency_is_not_offered(): void
    {
        $context = $this->context(paymentFeesRecorded: true, settlesInOrderCurrency: false);
        $service = new TicketResaleEligibilityService;

        $this->assertSame(TicketResaleBlock::CURRENCY_NOT_SUPPORTED, $service->blockOnOfferingResale($context));
        $this->assertSame(TicketResaleBlock::CURRENCY_NOT_SUPPORTED, $service->blockOnListing($context));
    }

    public function test_other_refusals_are_not_hidden_while_fees_are_missing(): void
    {
        $context = $this->context(paymentFeesRecorded: false, settlesInOrderCurrency: false, hasOnlyResaleRefunds: false);

        $this->assertSame(TicketResaleBlock::ORDER_REFUNDED, (new TicketResaleEligibilityService)->blockOnOfferingResale($context));
    }

    private function context(
        bool $allowResale = true,
        bool $resellableType = true,
        string $eventStatus = 'LIVE',
        string $priceType = 'PAID',
        bool $priceOnSale = true,
        bool $seated = false,
        string $orderStatus = 'COMPLETED',
        string $attendeeStatus = 'ACTIVE',
        bool $checkedIn = false,
        ?string $transferredAt = null,
        string $occurrenceStatus = 'ACTIVE',
        string $startsAt = '2026-12-25 20:00:00',
        int $cutoffMinutes = 120,
        bool $hasPendingTransfer = false,
        bool $hasOpenListing = false,
        bool $paidOnlineThroughStripe = true,
        bool $paidOnCurrentStripeAccount = true,
        bool $paymentFeesRecorded = true,
        bool $settlesInOrderCurrency = true,
        bool $hasOnlyResaleRefunds = true,
        int $sellerUnitGross = 2500,
        int $currentUnitGross = 2500,
    ): TicketResaleContextDTO {
        $settings = (new EventSettingDomainObject)
            ->setAllowTicketResale($allowResale)
            ->setTicketResaleCutoffMinutes($cutoffMinutes);

        return new TicketResaleContextDTO(
            event: (new EventDomainObject)->setStatus($eventStatus)->setEventSettings($settings),
            order: (new OrderDomainObject)->setStatus($orderStatus),
            attendee: (new AttendeeDomainObject)->setStatus($attendeeStatus)->setTransferredAt($transferredAt),
            product: (new ProductDomainObject)->setIsResellable($resellableType)->setType($priceType),
            occurrence: (new EventOccurrenceDomainObject)->setStatus($occurrenceStatus)->setStartDate($startsAt),
            is_checked_in: $checkedIn,
            is_seated: $seated,
            is_price_on_sale: $priceOnSale,
            has_pending_transfer: $hasPendingTransfer,
            has_open_listing: $hasOpenListing,
            paid_online_through_stripe: $paidOnlineThroughStripe,
            paid_on_current_stripe_account: $paidOnCurrentStripeAccount,
            payment_fees_recorded: $paymentFeesRecorded,
            settles_in_order_currency: $settlesInOrderCurrency,
            has_only_resale_refunds: $hasOnlyResaleRefunds,
            seller_unit_gross: $sellerUnitGross,
            current_unit_gross: $currentUnitGross,
            seller_unit_price: $sellerUnitGross / 100,
            current_unit_price: $currentUnitGross / 100,
            seller_order_gross: $sellerUnitGross,
            seller_order_unrefunded_balance: $sellerUnitGross,
            seller_order_card_fee: 0,
            connected_account_id: null,
        );
    }
}
