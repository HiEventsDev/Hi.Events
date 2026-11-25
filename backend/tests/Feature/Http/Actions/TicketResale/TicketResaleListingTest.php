<?php

namespace Tests\Feature\Http\Actions\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\TicketResaleBlock;
use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Enums\TicketTransferBlock;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Http\Actions\TicketTransfer\BaseTicketTransferPublicAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\TicketResale\TicketResaleNoticeMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferClaimService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use Stripe\Charge;
use Stripe\Service\ChargeService;
use Stripe\StripeClient;
use Tests\Concerns\VerifiesSelfService;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class TicketResaleListingTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use VerifiesSelfService;

    private const BUYER_EMAIL = 'buyer@example.test';

    private const GUEST_EMAIL = 'guest@example.test';

    private const TICKET_PRICE = 50.00;

    private const CARD_FEE = 3.20;

    private int $productId;

    private int $priceId;

    private int $occurrenceId;

    private int $orderId;

    private string $orderShortId;

    private array $ticket;

    private array $secondTicket;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'allow_ticket_resale' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null, price: self::TICKET_PRICE);
        $this->occurrenceId = $this->insertOccurrence(daysAhead: 30);

        $this->insertOrderPaidThroughStripe();
    }

    public function test_the_estimate_shows_what_was_paid_the_fees_of_the_replacement_sale_and_the_refund(): void
    {
        config(['app.saas_mode_enabled' => true]);
        $this->chargeApplicationFeesOnStripeAccount('acct_current', fixed: 0.30, percentage: 2.5);
        $this->addParkingAddOnToOrder(price: 10.00, cardFee: 3.52);

        $estimate = $this->getJson($this->resaleUrl($this->ticket), $this->buyerHeaders())->assertOk();

        $estimate->assertJsonPath('data.can_list', true);
        $estimate->assertJsonPath('data.block', null);
        $estimate->assertJsonPath('data.block_message', null);
        $estimate->assertJsonPath('data.currency', 'USD');
        $this->assertEqualsWithDelta(50.00, $estimate->json('data.amount_paid'), 0.0001);
        $this->assertEqualsWithDelta(3.15, $estimate->json('data.estimated_fees'), 0.0001);
        $this->assertEqualsWithDelta(46.85, $estimate->json('data.estimated_refund'), 0.0001);
        $estimate->assertJsonPath('data.add_ons', ['Parking']);
        $this->assertTrue($this->occurrenceStart()->subMinutes(120)->equalTo(Carbon::parse($estimate->json('data.cutoff'))));
    }

    public function test_the_estimate_explains_why_a_ticket_cannot_be_listed(): void
    {
        DB::table('products')->where('id', $this->productId)->update(['is_resellable' => false]);

        $this->getJson($this->resaleUrl($this->ticket), $this->buyerHeaders())
            ->assertOk()
            ->assertJsonPath('data.can_list', false)
            ->assertJsonPath('data.block', TicketResaleBlock::TICKET_TYPE_NOT_RESELLABLE->value)
            ->assertJsonPath('data.block_message', TicketResaleBlock::TICKET_TYPE_NOT_RESELLABLE->message());
    }

    public function test_a_buyer_lists_a_ticket_and_it_shows_as_listed_on_the_order_and_ticket_pages(): void
    {
        $domainEvents = [];
        $this->app['events']->listen(AttendeeEvent::class, function (AttendeeEvent $event) use (&$domainEvents): void {
            $domainEvents[] = [$event->type, $event->attendeeId];
        });
        $capacityChanges = [];
        $this->app['events']->listen(CapacityChangedEvent::class, function (CapacityChangedEvent $event) use (&$capacityChanges): void {
            $capacityChanges[] = $event;
        });
        Mail::fake();

        $this->assertTrue($this->ticketOnOrderPage($this->ticket)['resale']['can_list']);

        $this->list($this->ticket)
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->assertJsonPath('data.status', TicketResaleListingStatus::LISTED->name)
            ->assertJsonPath('data.can_list', false)
            ->assertJsonPath('data.held_until', null)
            ->assertJsonPath('data.withdrawal_requested', false);

        $listing = DB::table('ticket_resale_listings')->where('attendee_id', $this->ticket['id'])->first();
        $this->assertSame($this->eventId, $listing->event_id);
        $this->assertSame($this->orderId, $listing->order_id);
        $this->assertSame($this->priceId, $listing->product_price_id);
        $this->assertSame($this->occurrenceId, $listing->event_occurrence_id);
        $this->assertSame(TicketResaleListingStatus::LISTED->name, $listing->status);
        $this->assertEqualsWithDelta(50.00, (float) $listing->min_unit_price, 0.0001);
        $this->assertEqualsWithDelta(48.40, (float) $listing->estimated_refund, 0.0001);
        $this->assertNull($listing->connected_account_id);
        $this->assertNull($listing->claimed_by_order_id);
        $this->assertDatabaseHas('order_audit_logs', [
            'order_id' => $this->orderId,
            'attendee_id' => $this->ticket['id'],
            'action' => OrderAuditAction::TICKET_RESALE_LISTED->value,
        ]);
        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::BUYER_EMAIL)
            && $mail->envelope()->subject === TicketResaleNotice::LISTED_FOR_SELLER->subject());
        $this->assertContains([DomainEventType::ATTENDEE_RESALE_LISTED, $this->ticket['id']], $domainEvents);
        $this->assertEquals([new CapacityChangedEvent(
            eventId: $this->eventId,
            direction: CapacityChangeDirection::INCREASED,
            productId: $this->productId,
            productPriceId: $this->priceId,
            eventOccurrenceId: $this->occurrenceId,
        )], $capacityChanges);

        $onOrderPage = $this->ticketOnOrderPage($this->ticket);
        $this->assertSame(TicketResaleListingStatus::LISTED->name, $onOrderPage['resale']['status']);
        $this->assertFalse($onOrderPage['resale']['can_list']);
        $this->assertNull($this->ticketOnOrderPage($this->secondTicket)['resale']['status']);
        $this->assertTrue($this->ticketOnOrderPage($this->secondTicket)['resale']['can_list']);
        $this->getJson("/public/events/{$this->eventId}/attendees/{$this->ticket['short_id']}")
            ->assertOk()
            ->assertJsonPath('data.resale.status', TicketResaleListingStatus::LISTED->name)
            ->assertJsonPath('data.resale.can_list', false);
    }

    public function test_the_guest_named_on_a_listed_ticket_is_told_it_cannot_be_used_and_sees_no_amount(): void
    {
        Mail::fake();

        $this->list($this->ticket)->assertStatus(ResponseCodes::HTTP_CREATED);

        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::GUEST_EMAIL)
            && $mail->envelope()->subject === TicketResaleNotice::LISTED_FOR_HOLDER->subject()
            && ! str_contains($mail->content()->with['summary'], '$'));
    }

    public function test_the_buyer_named_on_their_own_listed_ticket_gets_one_email(): void
    {
        Mail::fake();

        $this->list($this->secondTicket)->assertStatus(ResponseCodes::HTTP_CREATED);

        Mail::assertQueued(TicketResaleNoticeMail::class, 1);
    }

    public function test_a_listed_tickets_email_cannot_be_changed_from_the_order_page(): void
    {
        $this->insertListing($this->ticket);

        $this->patchJson(
            "/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/{$this->ticket['short_id']}",
            ['email' => 'someone.else@example.test'],
            $this->buyerHeaders(),
        )->assertStatus(ResponseCodes::HTTP_CONFLICT);

        $this->assertDatabaseHas('attendees', ['id' => $this->ticket['id'], 'email' => self::GUEST_EMAIL]);
    }

    public function test_a_ticket_link_never_offers_to_list_a_ticket(): void
    {
        $this->getJson("/public/events/{$this->eventId}/attendees/{$this->ticket['short_id']}")
            ->assertOk()
            ->assertJsonPath('data.resale.can_list', false)
            ->assertJsonPath('data.resale.status', null);
    }

    public function test_the_listing_keeps_the_lower_of_the_price_paid_and_todays_price_as_its_minimum(): void
    {
        DB::table('product_prices')->where('id', $this->priceId)->update(['price' => 40.00]);
        Mail::fake();

        $this->list($this->ticket)->assertStatus(ResponseCodes::HTTP_CREATED);

        $this->assertEqualsWithDelta(
            40.00,
            (float) DB::table('ticket_resale_listings')->where('attendee_id', $this->ticket['id'])->value('min_unit_price'),
            0.0001,
        );
    }

    public function test_listing_estimating_and_withdrawing_need_a_verified_session(): void
    {
        foreach (['getJson', 'postJson', 'deleteJson'] as $request) {
            $this->{$request}($this->resaleUrl($this->ticket))
                ->assertStatus(ResponseCodes::HTTP_FORBIDDEN)
                ->assertJsonPath('error_code', BaseTicketTransferPublicAction::VERIFICATION_REQUIRED);
        }

        $this->assertDatabaseMissing('ticket_resale_listings', ['order_id' => $this->orderId]);
    }

    public function test_a_guest_verified_as_the_tickets_own_address_cannot_list_or_withdraw_it(): void
    {
        $guest = $this->selfServiceHeadersFor(self::GUEST_EMAIL);

        $this->getJson($this->resaleUrl($this->ticket), $guest)->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
        $this->postJson($this->resaleUrl($this->ticket), [], $guest)
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN)
            ->assertJsonPath('error_code', BaseTicketTransferPublicAction::VERIFICATION_REQUIRED);
        $this->assertDatabaseMissing('ticket_resale_listings', ['order_id' => $this->orderId]);

        $this->insertListing($this->ticket);
        $this->deleteJson($this->resaleUrl($this->ticket), [], $guest)->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('ticket_resale_listings', [
            'attendee_id' => $this->ticket['id'],
            'status' => TicketResaleListingStatus::LISTED->name,
        ]);
    }

    public function test_a_ticket_on_another_order_link_is_not_found(): void
    {
        $otherOrderShortId = DB::table('orders')->where('id', $this->insertOrder(OrderStatus::COMPLETED->name))->value('short_id');

        $this->postJson(
            "/public/events/{$this->eventId}/order/$otherOrderShortId/attendees/{$this->ticket['short_id']}/resale",
            [],
            $this->buyerHeaders(),
        )->assertNotFound();
    }

    public function test_a_ticket_cannot_be_listed_when_resale_is_off_for_the_event(): void
    {
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_ticket_resale' => false]);

        $this->assertListingRefused(TicketResaleBlock::RESALE_DISABLED);
        $this->assertFalse($this->ticketOnOrderPage($this->ticket)['resale']['can_list']);
    }

    public function test_a_ticket_cannot_be_listed_when_its_ticket_type_is_not_resellable(): void
    {
        DB::table('products')->where('id', $this->productId)->update(['is_resellable' => false]);

        $this->assertListingRefused(TicketResaleBlock::TICKET_TYPE_NOT_RESELLABLE);
        $this->assertFalse($this->ticketOnOrderPage($this->ticket)['resale']['can_list']);
    }

    public function test_a_free_ticket_cannot_be_listed(): void
    {
        DB::table('products')->where('id', $this->productId)->update(['type' => 'FREE']);
        DB::table('product_prices')->where('id', $this->priceId)->update(['price' => 0]);
        DB::table('order_items')->where('order_id', $this->orderId)->update(['price' => 0, 'total_before_additions' => 0, 'total_gross' => 0]);

        $this->assertListingRefused(TicketResaleBlock::PRICE_TYPE_NOT_SUPPORTED);
    }

    public function test_a_ticket_that_cost_nothing_on_a_paid_ticket_type_cannot_be_listed(): void
    {
        DB::table('order_items')->where('order_id', $this->orderId)->update(['price' => 0, 'total_before_additions' => 0, 'total_gross' => 0]);

        $this->assertListingRefused(TicketResaleBlock::NOTHING_TO_REFUND);
    }

    public function test_a_ticket_on_an_order_that_was_not_paid_through_stripe_cannot_be_listed(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['payment_provider' => PaymentProviders::OFFLINE->value]);
        $this->assertListingRefused(TicketResaleBlock::NOT_PAID_ONLINE);

        DB::table('orders')->where('id', $this->orderId)->update([
            'payment_provider' => PaymentProviders::STRIPE->value,
            'payment_link_created_at' => now(),
        ]);
        $this->assertListingRefused(TicketResaleBlock::NOT_PAID_ONLINE);

        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => null]);
        DB::table('stripe_payments')->where('order_id', $this->orderId)->update(['charge_id' => null]);
        $this->assertListingRefused(TicketResaleBlock::NOT_PAID_ONLINE);
    }

    public function test_a_ticket_paid_on_a_stripe_account_the_organizer_has_replaced_cannot_be_listed(): void
    {
        DB::table('stripe_payments')->where('order_id', $this->orderId)->update(['connected_account_id' => 'acct_previous']);

        $this->assertListingRefused(TicketResaleBlock::STRIPE_ACCOUNT_CHANGED);
    }

    public function test_a_ticket_cannot_be_listed_when_the_card_fee_was_not_settled_in_the_order_currency(): void
    {
        DB::table('order_payment_platform_fees')->where('order_id', $this->orderId)->update(['currency' => 'EUR']);

        $this->assertListingRefused(TicketResaleBlock::CURRENCY_NOT_SUPPORTED);
    }

    public function test_a_ticket_that_was_transferred_to_its_holder_cannot_be_listed(): void
    {
        DB::table('attendees')->where('id', $this->ticket['id'])->update(['transferred_at' => now()]);

        $this->assertListingRefused(TicketResaleBlock::TRANSFERRED);
    }

    public function test_a_ticket_with_a_transfer_waiting_to_be_accepted_cannot_be_listed(): void
    {
        DB::table('ticket_transfers')->insert([
            'event_id' => $this->eventId,
            'order_id' => $this->orderId,
            'attendee_id' => $this->ticket['id'],
            'sender_email' => self::BUYER_EMAIL,
            'recipient_email' => 'recipient@example.test',
            'token_hash' => TicketTransferClaimService::hashToken('tt_pending'),
            'status' => 'PENDING',
            'locale' => 'en',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertListingRefused(TicketResaleBlock::TRANSFER_PENDING);
    }

    public function test_a_ticket_that_is_already_listed_cannot_be_listed_again(): void
    {
        $this->insertListing($this->ticket);

        $this->list($this->ticket)
            ->assertStatus(ResponseCodes::HTTP_CONFLICT)
            ->assertJsonPath('message', TicketResaleBlock::ALREADY_LISTED->message());
        $this->assertSame(1, DB::table('ticket_resale_listings')->where('attendee_id', $this->ticket['id'])->count());
    }

    public function test_a_checked_in_ticket_cannot_be_listed(): void
    {
        DB::table('attendee_check_ins')->insert([
            'short_id' => 'ci_'.uniqid(),
            'check_in_list_id' => DB::table('check_in_lists')->insertGetId([
                'short_id' => 'cil_'.uniqid(),
                'name' => 'Door',
                'event_id' => $this->eventId,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'product_id' => $this->productId,
            'attendee_id' => $this->ticket['id'],
            'event_id' => $this->eventId,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertListingRefused(TicketResaleBlock::CHECKED_IN);
    }

    public function test_a_ticket_the_organizer_checked_in_by_hand_cannot_be_listed(): void
    {
        DB::table('attendees')->where('id', $this->ticket['id'])->update(['checked_in_at' => now()]);

        $this->assertListingRefused(TicketResaleBlock::CHECKED_IN);
    }

    public function test_a_ticket_cannot_be_listed_after_the_resale_cutoff(): void
    {
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['ticket_resale_cutoff_minutes' => 60 * 24 * 31]);

        $this->assertListingRefused(TicketResaleBlock::CUTOFF_PASSED);
    }

    public function test_a_ticket_whose_ticket_type_is_no_longer_on_sale_cannot_be_listed(): void
    {
        DB::table('products')->where('id', $this->productId)->update(['sale_end_date' => now()->subDay()]);

        $this->assertListingRefused(TicketResaleBlock::TICKET_TYPE_NOT_ON_SALE);
    }

    public function test_a_ticket_on_a_tier_locked_behind_an_earlier_tier_can_be_listed_once_that_tier_sells_out(): void
    {
        DB::table('products')->where('id', $this->productId)->update(['type' => 'TIERED', 'sequential_tier_release_enabled' => true]);
        DB::table('product_prices')->where('id', $this->priceId)->update(['order' => 2, 'label' => 'Second release']);
        $firstReleaseId = $this->insertPrice($this->productId, 3, label: 'First release', price: 40.00);
        DB::table('product_prices')->where('id', $firstReleaseId)->update(['order' => 1]);

        $this->assertListingRefused(TicketResaleBlock::TICKET_TYPE_NOT_ON_SALE);

        DB::table('product_prices')->where('id', $firstReleaseId)->update(['quantity_sold' => 3]);
        Mail::fake();

        $this->list($this->ticket)->assertStatus(ResponseCodes::HTTP_CREATED);
    }

    public function test_a_ticket_on_an_order_the_organizer_has_partly_refunded_cannot_be_listed(): void
    {
        $this->insertRefund(amount: 10.00, metadata: ['payment_intent' => 'pi_seller']);

        $this->assertListingRefused(TicketResaleBlock::ORDER_REFUNDED);
    }

    public function test_a_ticket_on_an_order_with_a_failed_resale_refund_cannot_be_listed(): void
    {
        $this->insertListing($this->secondTicket, [
            'status' => TicketResaleListingStatus::SOLD->name,
            'refund_status' => TicketResaleRefundStatus::FAILED->name,
        ]);

        $this->assertListingRefused(TicketResaleBlock::ORDER_REFUNDED);
    }

    public function test_a_second_ticket_can_be_listed_after_the_first_was_resold_and_refunded(): void
    {
        $soldListingId = $this->insertListing($this->ticket, [
            'status' => TicketResaleListingStatus::SOLD->name,
            'refund_status' => TicketResaleRefundStatus::SUCCEEDED->name,
            'refund_amount' => 48.40,
        ]);
        $this->insertRefund(amount: 48.40, metadata: [
            StripePaymentIntentRefundService::RESALE_LISTING_METADATA_KEY => (string) $soldListingId,
            'payment_intent' => 'pi_seller',
        ]);
        Mail::fake();

        $this->list($this->secondTicket)
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->assertJsonPath('data.status', TicketResaleListingStatus::LISTED->name);

        $this->assertSame(TicketResaleListingStatus::SOLD->name, $this->ticketOnOrderPage($this->ticket)['resale']['status']);
        $this->assertSame(TicketResaleListingStatus::LISTED->name, $this->ticketOnOrderPage($this->secondTicket)['resale']['status']);
    }

    public function test_the_refund_estimate_never_exceeds_what_is_left_unrefunded_on_the_order(): void
    {
        $soldListingId = $this->insertListing($this->ticket, [
            'status' => TicketResaleListingStatus::SOLD->name,
            'refund_status' => TicketResaleRefundStatus::SUCCEEDED->name,
        ]);
        $this->insertRefund(amount: 70.00, metadata: [
            StripePaymentIntentRefundService::RESALE_LISTING_METADATA_KEY => (string) $soldListingId,
        ]);

        $estimate = $this->getJson($this->resaleUrl($this->secondTicket), $this->buyerHeaders())->assertOk();

        $this->assertEqualsWithDelta(30.00, $estimate->json('data.estimated_refund'), 0.0001);
    }

    public function test_withdrawing_a_listing_nobody_holds_closes_it_at_once(): void
    {
        $this->insertListing($this->ticket);

        $this->deleteJson($this->resaleUrl($this->ticket), [], $this->buyerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', null)
            ->assertJsonPath('data.can_list', true)
            ->assertJsonPath('data.withdrawal_requested', false);

        $this->assertDatabaseHas('ticket_resale_listings', [
            'attendee_id' => $this->ticket['id'],
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::WITHDRAWN->value,
        ]);
        $this->assertDatabaseHas('order_audit_logs', [
            'attendee_id' => $this->ticket['id'],
            'action' => OrderAuditAction::TICKET_RESALE_WITHDRAWN->value,
        ]);
        $this->assertNull($this->ticketOnOrderPage($this->ticket)['resale']['status']);
        $this->deleteJson($this->resaleUrl($this->ticket), [], $this->buyerHeaders())->assertNotFound();
    }

    public function test_the_guest_named_on_a_withdrawn_ticket_is_told_it_works_again(): void
    {
        $this->insertListing($this->ticket);
        Mail::fake();

        $this->deleteJson($this->resaleUrl($this->ticket), [], $this->buyerHeaders())->assertOk();

        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::GUEST_EMAIL)
            && $mail->envelope()->subject === TicketResaleNotice::RETURNED_FOR_HOLDER->subject());
    }

    public function test_withdrawing_a_listing_a_buyer_is_checking_out_with_waits_for_that_checkout(): void
    {
        $heldUntil = now()->addMinutes(10)->startOfSecond();
        $this->insertListing($this->ticket, [
            'claimed_by_order_id' => $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1, reservedUntil: $heldUntil),
        ]);

        $withdrawal = $this->deleteJson($this->resaleUrl($this->ticket), [], $this->buyerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', TicketResaleListingStatus::LISTED->name)
            ->assertJsonPath('data.withdrawal_requested', true)
            ->assertJsonPath('data.can_list', false);

        $this->assertTrue($heldUntil->equalTo(Carbon::parse($withdrawal->json('data.held_until'))));
        $requestedAt = DB::table('ticket_resale_listings')->where('attendee_id', $this->ticket['id'])->value('withdrawal_requested_at');
        $this->assertNotNull($requestedAt);
        $this->assertDatabaseHas('ticket_resale_listings', [
            'attendee_id' => $this->ticket['id'],
            'status' => TicketResaleListingStatus::LISTED->name,
            'closed_reason' => null,
        ]);

        $this->travel(5)->seconds();
        $this->deleteJson($this->resaleUrl($this->ticket), [], $this->buyerHeaders())->assertOk();

        $this->assertSame(
            $requestedAt,
            DB::table('ticket_resale_listings')->where('attendee_id', $this->ticket['id'])->value('withdrawal_requested_at'),
        );
        $this->assertSame(1, DB::table('order_audit_logs')
            ->where('attendee_id', $this->ticket['id'])
            ->where('action', OrderAuditAction::TICKET_RESALE_WITHDRAWN->value)
            ->count());
    }

    public function test_a_listing_whose_checkout_hold_has_lapsed_is_withdrawn_at_once(): void
    {
        $this->insertListing($this->ticket, [
            'claimed_by_order_id' => $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1, reservedUntil: now()->subMinute()),
        ]);

        $this->deleteJson($this->resaleUrl($this->ticket), [], $this->buyerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', null);

        $this->assertDatabaseHas('ticket_resale_listings', [
            'attendee_id' => $this->ticket['id'],
            'status' => TicketResaleListingStatus::CLOSED->name,
        ]);
    }

    public function test_a_listed_row_that_no_longer_locks_the_ticket_reads_as_not_listed(): void
    {
        $this->insertListing($this->ticket);
        $this->assertSame(TicketResaleListingStatus::LISTED->name, $this->ticketOnOrderPage($this->ticket)['resale']['status']);

        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_ticket_resale' => false]);

        $onOrderPage = $this->ticketOnOrderPage($this->ticket);
        $this->assertNull($onOrderPage['resale']['status']);
        $this->assertFalse($onOrderPage['resale']['can_list']);
        $this->getJson("/public/events/{$this->eventId}/attendees/{$this->ticket['short_id']}")
            ->assertOk()
            ->assertJsonPath('data.resale.status', null);
    }

    public function test_a_listed_ticket_cannot_be_transferred(): void
    {
        $this->insertListing($this->ticket);

        $this->postJson(
            "/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/{$this->ticket['short_id']}/transfer",
            ['recipient_email' => 'recipient@example.test'],
            $this->buyerHeaders(),
        )
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.recipient_email.0', TicketTransferBlock::LISTED_FOR_RESALE->message());

        $this->assertDatabaseMissing('ticket_transfers', ['attendee_id' => $this->ticket['id']]);
        $onOrderPage = $this->ticketOnOrderPage($this->ticket);
        $this->assertFalse($onOrderPage['transfer']['can_start']);
        $this->assertSame(TicketTransferBlock::LISTED_FOR_RESALE->message(), $onOrderPage['transfer']['blocked_reason']);
        $this->assertTrue($this->ticketOnOrderPage($this->secondTicket)['transfer']['can_start']);
    }

    public function test_a_ticket_whose_fee_record_is_missing_is_offered_and_the_fees_are_fetched_from_stripe_when_the_buyer_asks_for_an_estimate(): void
    {
        DB::table('order_payment_platform_fees')->where('order_id', $this->orderId)->delete();
        $this->fakeStripeCharge(balanceTransaction: [
            'id' => 'txn_backfill',
            'object' => 'balance_transaction',
            'amount' => 5000,
            'net' => 4750,
            'fee' => 250,
            'currency' => 'usd',
            'fee_details' => [
                ['type' => 'stripe_fee', 'amount' => 175, 'currency' => 'usd'],
                ['type' => 'application_fee', 'amount' => 75, 'currency' => 'usd'],
            ],
        ]);

        $this->assertTrue($this->ticketOnOrderPage($this->ticket)['resale']['can_list']);
        $this->assertDatabaseMissing('order_payment_platform_fees', ['order_id' => $this->orderId]);

        $this->getJson($this->resaleUrl($this->ticket), $this->buyerHeaders())
            ->assertOk()
            ->assertJsonPath('data.can_list', true);

        $this->assertDatabaseHas('order_payment_platform_fees', [
            'order_id' => $this->orderId,
            'payment_platform_fee_amount' => 1.75,
            'currency' => 'USD',
        ]);
        $this->list($this->ticket)->assertStatus(ResponseCodes::HTTP_CREATED);
    }

    public function test_a_ticket_cannot_be_listed_while_stripe_has_not_settled_its_payment_fees(): void
    {
        DB::table('order_payment_platform_fees')->where('order_id', $this->orderId)->delete();
        $this->fakeStripeCharge(balanceTransaction: null);

        $this->getJson($this->resaleUrl($this->ticket), $this->buyerHeaders())
            ->assertOk()
            ->assertJsonPath('data.can_list', false)
            ->assertJsonPath('data.block', TicketResaleBlock::PAYMENT_NOT_CONFIRMED->value);
        $this->list($this->ticket)
            ->assertStatus(ResponseCodes::HTTP_CONFLICT)
            ->assertJsonPath('message', TicketResaleBlock::PAYMENT_NOT_CONFIRMED->message());
    }

    private function fakeStripeCharge(?array $balanceTransaction): void
    {
        $charges = new class(Mockery::mock(StripeClient::class), $balanceTransaction) extends ChargeService
        {
            public function __construct($client, private readonly ?array $balanceTransaction)
            {
                parent::__construct($client);
            }

            public function retrieve($id, $params = null, $opts = null)
            {
                return Charge::constructFrom(['id' => $id, 'object' => 'charge', 'balance_transaction' => $this->balanceTransaction]);
            }
        };

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('charges')->andReturn($charges);
        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForPlatform')->andReturn($stripeClient);
        $this->app->instance(StripeClientFactory::class, $stripeClientFactory);
    }

    private function assertListingRefused(TicketResaleBlock $block): void
    {
        $this->list($this->ticket)
            ->assertStatus(ResponseCodes::HTTP_CONFLICT)
            ->assertJsonPath('message', $block->message());

        $this->getJson($this->resaleUrl($this->ticket), $this->buyerHeaders())
            ->assertOk()
            ->assertJsonPath('data.can_list', false)
            ->assertJsonPath('data.block', $block->value);

        $this->assertDatabaseMissing('ticket_resale_listings', [
            'attendee_id' => $this->ticket['id'],
            'status' => TicketResaleListingStatus::LISTED->name,
        ]);
    }

    private function list(array $ticket): TestResponse
    {
        return $this->postJson($this->resaleUrl($ticket), [], $this->buyerHeaders());
    }

    private function resaleUrl(array $ticket): string
    {
        return "/public/events/{$this->eventId}/order/{$this->orderShortId}/attendees/{$ticket['short_id']}/resale";
    }

    private function buyerHeaders(): array
    {
        return $this->selfServiceHeadersFor(self::BUYER_EMAIL);
    }

    private function ticketOnOrderPage(array $ticket): array
    {
        return collect(
            $this->getJson("/public/events/{$this->eventId}/order/{$this->orderShortId}?include=event")->assertOk()->json('data.attendees')
        )->firstWhere('id', $ticket['id']);
    }

    private function occurrenceStart(): Carbon
    {
        return Carbon::parse(DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('start_date'), 'UTC');
    }

    private function insertOrderPaidThroughStripe(): void
    {
        $this->orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->orderShortId = DB::table('orders')->where('id', $this->orderId)->value('short_id');

        DB::table('orders')->where('id', $this->orderId)->update([
            'email' => self::BUYER_EMAIL,
            'first_name' => 'Bea',
            'last_name' => 'Buyer',
            'payment_provider' => PaymentProviders::STRIPE->value,
            'payment_status' => 'PAYMENT_RECEIVED',
            'total_before_additions' => 2 * self::TICKET_PRICE,
            'total_gross' => 2 * self::TICKET_PRICE,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
            'product_type' => ProductType::TICKET->name,
            'event_occurrence_id' => $this->occurrenceId,
            'item_name' => 'General Admission',
            'quantity' => 2,
            'price' => self::TICKET_PRICE,
            'total_before_additions' => 2 * self::TICKET_PRICE,
            'total_gross' => 2 * self::TICKET_PRICE,
        ]);

        DB::table('stripe_payments')->insert([
            'order_id' => $this->orderId,
            'payment_intent_id' => 'pi_'.uniqid(),
            'charge_id' => 'ch_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_payment_platform_fees')->insert([
            'order_id' => $this->orderId,
            'payment_platform' => 'stripe',
            'payment_platform_fee_amount' => self::CARD_FEE,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->ticket = $this->insertTicket(self::GUEST_EMAIL);
        $this->secondTicket = $this->insertTicket(self::BUYER_EMAIL);
    }

    private function insertTicket(string $email): array
    {
        $ticket = [
            'short_id' => 'att_'.uniqid(),
            'public_id' => 'ATT_'.uniqid(),
        ];

        return $ticket + ['id' => DB::table('attendees')->insertGetId($ticket + [
            'email' => $email,
            'first_name' => 'Tess',
            'last_name' => 'Ticket',
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ])];
    }

    private function insertListing(array $ticket, array $overrides = []): int
    {
        return DB::table('ticket_resale_listings')->insertGetId($overrides + [
            'event_id' => $this->eventId,
            'order_id' => $this->orderId,
            'attendee_id' => $ticket['id'],
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'status' => TicketResaleListingStatus::LISTED->name,
            'min_unit_price' => self::TICKET_PRICE,
            'estimated_refund' => 48.40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRefund(float $amount, array $metadata): void
    {
        DB::table('orders')->where('id', $this->orderId)->update([
            'refund_status' => OrderRefundStatus::PARTIALLY_REFUNDED->name,
            'total_refunded' => $amount,
        ]);

        DB::table('order_refunds')->insert([
            'order_id' => $this->orderId,
            'payment_provider' => PaymentProviders::STRIPE->value,
            'refund_id' => 're_'.uniqid(),
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'succeeded',
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function chargeApplicationFeesOnStripeAccount(string $stripeAccountId, float $fixed, float $percentage): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update([
            'organizer_configuration_id' => DB::table('organizer_configurations')->insertGetId([
                'name' => 'Resale test fees',
                'application_fees' => json_encode(['fixed' => $fixed, 'percentage' => $percentage, 'currency' => 'USD']),
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ]);

        DB::table('organizer_stripe_platforms')->insert([
            'organizer_id' => $this->organizerId,
            'stripe_account_id' => $stripeAccountId,
            'stripe_setup_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stripe_payments')->where('order_id', $this->orderId)->update(['connected_account_id' => $stripeAccountId]);
    }

    private function addParkingAddOnToOrder(float $price, float $cardFee): void
    {
        $parkingId = $this->insertProduct(ProductType::GENERAL->name);

        DB::table('order_items')->insert([
            'order_id' => $this->orderId,
            'product_id' => $parkingId,
            'product_price_id' => $this->insertPrice($parkingId, null, price: $price),
            'product_type' => ProductType::GENERAL->name,
            'event_occurrence_id' => $this->occurrenceId,
            'item_name' => 'Parking',
            'quantity' => 1,
            'price' => $price,
            'total_before_additions' => $price,
            'total_gross' => $price,
        ]);

        DB::table('orders')->where('id', $this->orderId)->update(['total_gross' => 2 * self::TICKET_PRICE + $price]);
        DB::table('order_payment_platform_fees')->where('order_id', $this->orderId)->update(['payment_platform_fee_amount' => $cardFee]);
    }
}
