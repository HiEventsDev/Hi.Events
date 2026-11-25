<?php

namespace Tests\Feature\Http\Actions\TicketResale;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ResaleRefundOutcome;
use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedOrderCompletionGuard;
use HiEvents\Exceptions\PaymentRefundedException;
use HiEvents\Jobs\TicketResale\ProcessResaleRefundJob;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Mail\TicketResale\TicketResaleNoticeMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\TicketResale\ResaleRefundService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Stripe\Charge;
use Stripe\Exception\ApiConnectionException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Service\ChargeService;
use Stripe\StripeClient;
use Tests\Feature\Support\FakeStripeRefunds;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class ResaleCompletionAndRefundTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const SELLER_EMAIL = 'seller@example.test';

    private const ORGANIZER_EMAIL = 'organizer@example.test';

    private const STRIPE_ACCOUNT = 'acct_resale';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $sellerOrderId;

    private string $checkInListShortId;

    private FakeStripeRefunds $stripeRefunds;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ProcessResaleRefundJob::class]);

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        DB::table('organizers')->where('id', $this->organizerId)->update(['email' => self::ORGANIZER_EMAIL]);
        DB::table('organizer_stripe_platforms')->insert([
            'organizer_id' => $this->organizerId,
            'stripe_account_id' => self::STRIPE_ACCOUNT,
            'stripe_setup_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'allow_ticket_resale' => true,
            'ticket_resale_cutoff_minutes' => 120,
            'payment_providers' => json_encode([PaymentProviders::STRIPE->name, PaymentProviders::OFFLINE->name]),
            'payment_links_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'sales_total_gross' => 100,
            'sales_total_before_additions' => 100,
            'products_sold' => 2,
            'attendees_registered' => 2,
            'created_at' => now(),
        ]);

        $this->occurrenceId = $this->insertOccurrence(daysAhead: 30);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, 2, ProductQuantityAppliesTo::EVENT->name, quantitySold: 2, price: 50.00);

        $this->sellerOrderId = $this->sellTickets($this->productId, $this->priceId, $this->occurrenceId, 2);
        DB::table('orders')->where('id', $this->sellerOrderId)->update([
            'email' => self::SELLER_EMAIL,
            'first_name' => 'Sam',
            'last_name' => 'Seller',
            'locale' => 'en',
            'total_gross' => 100,
            'total_before_additions' => 100,
            'total_refunded' => 0,
            'payment_provider' => PaymentProviders::STRIPE->name,
            'payment_status' => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);
        DB::table('order_items')->where('order_id', $this->sellerOrderId)->update(['price' => 50, 'total_before_additions' => 100, 'total_gross' => 100]);
        DB::table('stripe_payments')->insert([
            'order_id' => $this->sellerOrderId,
            'payment_intent_id' => 'pi_seller',
            'charge_id' => 'ch_seller',
            'connected_account_id' => self::STRIPE_ACCOUNT,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->checkInListShortId = 'cil_'.uniqid();
        DB::table('check_in_lists')->insert([
            'short_id' => $this->checkInListShortId,
            'name' => 'Door',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->stripeRefunds = new FakeStripeRefunds;
        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('refunds')->andReturn($this->stripeRefunds);
        $stripeClient->shouldReceive('getService')->with('charges')->andReturn($this->fakeStripeCharges());
        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForPlatform')->andReturn($stripeClient);
        $this->app->instance(StripeClientFactory::class, $stripeClientFactory);
    }

    public function test_a_paid_checkout_voids_the_sellers_ticket_and_refunds_them_what_they_paid_minus_the_new_sales_fees(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $events = $this->recordAttendeeEvents();

        $this->pay($buyerOrderId);
        Mail::fake();

        $sellerAttendeeId = $this->sellerAttendeeId();
        $this->assertDatabaseHas('attendees', ['id' => $sellerAttendeeId, 'status' => AttendeeStatus::CANCELLED->name]);
        $this->assertDatabaseHas('attendees', ['order_id' => $buyerOrderId, 'status' => AttendeeStatus::ACTIVE->name]);
        $this->assertDatabaseHas('orders', ['id' => $buyerOrderId, 'status' => OrderStatus::COMPLETED->name]);
        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::SOLD->name,
            'sold_order_id' => $buyerOrderId,
            'refund_status' => TicketResaleRefundStatus::PENDING->name,
        ]);
        $this->assertSame(2, (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold'));
        $this->assertSame(2, (int) DB::table('event_statistics')->where('event_id', $this->eventId)->value('attendees_registered'));
        $this->assertContains([DomainEventType::ATTENDEE_RESOLD, $sellerAttendeeId], $events->all());
        $this->assertContains([DomainEventType::ATTENDEE_CANCELLED, $sellerAttendeeId], $events->all());
        Queue::assertPushed(ProcessResaleRefundJob::class, 1);

        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($listingId));

        $this->assertSame([4750], array_column($this->stripeRefunds->created, 'amount'));
        $this->assertSame('pi_seller', $this->stripeRefunds->created[0]->payment_intent);
        $this->assertSame((string) $listingId, $this->stripeRefunds->created[0]->metadata['resale_listing_id']);
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'stripe_refund_id' => 're_1', 'refund_amount' => 47.50]);
        $this->assertDatabaseHas('orders', ['id' => $this->sellerOrderId, 'refund_status' => OrderRefundStatus::REFUND_PENDING->name]);
        $this->assertSellerNotified(TicketResaleNotice::SOLD_FOR_SELLER, '$47.50');

        $this->stripeSends('re_1', 'succeeded');

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::SUCCEEDED->name]);
        $this->assertDatabaseHas('orders', [
            'id' => $this->sellerOrderId,
            'refund_status' => OrderRefundStatus::PARTIALLY_REFUNDED->name,
            'total_refunded' => 47.50,
        ]);
        Mail::assertNotQueued(OrderRefunded::class);
        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($listingId));
        $this->assertCount(1, $this->stripeRefunds->created);
    }

    public function test_the_guest_named_on_a_resold_ticket_is_told_it_no_longer_works(): void
    {
        $this->list();
        $guestEmail = DB::table('attendees')->where('id', $this->sellerAttendeeId())->value('email');
        $buyerOrderId = $this->startCheckout('buyer');
        $sent = collect();
        $this->app['events']->listen(MessageSending::class, function (MessageSending $message) use ($sent): void {
            $sent->push([$message->message->getTo()[0]->getAddress(), $message->message->getSubject()]);
        });

        $this->pay($buyerOrderId);

        $this->assertContains([$guestEmail, TicketResaleNotice::SOLD_FOR_HOLDER->subject()], $sent->all());
    }

    public function test_a_seller_owed_nothing_is_still_told_their_ticket_sold(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        DB::table('orders')->where('id', $this->sellerOrderId)->update([
            'total_refunded' => 100,
            'refund_status' => OrderRefundStatus::REFUNDED->name,
        ]);
        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        Mail::fake();

        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($listingId));

        $this->assertCount(0, $this->stripeRefunds->created);
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::SUCCEEDED->name, 'refund_amount' => 0]);
        $this->assertSellerNotified(TicketResaleNotice::SOLD_WITHOUT_REFUND_FOR_SELLER);
    }

    public function test_a_listed_tickets_email_cannot_be_changed_by_the_organizer(): void
    {
        $this->list();
        $sellerAttendeeId = $this->sellerAttendeeId();

        $this->organizerRequest('patch', "/events/{$this->eventId}/attendees/{$sellerAttendeeId}", ['email' => 'someone.else@example.test'])
            ->assertJsonValidationErrors(['email']);
        $this->organizerRequest('put', "/events/{$this->eventId}/attendees/{$sellerAttendeeId}", [
            'first_name' => 'Sam',
            'last_name' => 'Seller',
            'email' => 'someone.else@example.test',
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
        ])->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('attendees', ['id' => $sellerAttendeeId, 'email' => 'someone.else@example.test']);
    }

    public function test_two_tickets_from_one_order_resold_together_are_refunded_one_after_the_other(): void
    {
        $firstListingId = $this->list();
        $secondListingId = $this->list(attendeeOffset: 1);
        $buyerOrderId = $this->startCheckout('buyer', quantity: 2);

        $this->pay($buyerOrderId, amount: 10000);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);

        Queue::assertPushed(ProcessResaleRefundJob::class, 2);
        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($firstListingId));
        $this->assertSame(ResaleRefundOutcome::NOT_READY, $this->processRefund($secondListingId));
        $this->assertCount(1, $this->stripeRefunds->created);

        $this->stripeSends('re_1', 'succeeded');
        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($secondListingId));
        $this->stripeSends('re_2', 'succeeded');

        $this->assertSame([4875, 4875], array_column($this->stripeRefunds->created, 'amount'));
        $this->assertDatabaseHas('orders', ['id' => $this->sellerOrderId, 'total_refunded' => 97.50]);
        $this->assertSame(2, DB::table('ticket_resale_listings')->where('refund_status', TicketResaleRefundStatus::SUCCEEDED->name)->whereIn('id', [$firstListingId, $secondListingId])->count());
        $this->assertSame(2, (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold'));
    }

    public function test_a_refund_waiting_for_the_new_sales_fees_starts_as_soon_as_they_are_recorded(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);
        $this->assertSame(ResaleRefundOutcome::NOT_READY, $this->processRefund($listingId));
        Queue::assertPushed(ProcessResaleRefundJob::class, 1);

        $this->app->make(ChargeSucceededHandler::class)->handleEvent(Charge::constructFrom(array_merge(
            $this->fakeStripeCharges()->retrieve('ch_buyer_'.$buyerOrderId)->toArray(),
            ['payment_intent' => 'pi_buyer_'.$buyerOrderId, 'status' => 'succeeded'],
        )));

        Queue::assertPushed(ProcessResaleRefundJob::class, 2);
        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($listingId));
        $this->assertCount(1, $this->stripeRefunds->created);
    }

    public function test_a_refund_still_waiting_for_fees_is_not_restarted_by_the_scheduled_command(): void
    {
        $listingId = $this->list();
        $this->pay($this->startCheckout('buyer'));

        $this->travel(70)->minutes();
        $this->assertSame(ResaleRefundOutcome::NOT_READY, $this->processRefund($listingId, attempt: 2));
        $this->travel(70)->minutes();
        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        Queue::assertPushed(ProcessResaleRefundJob::class, 1);
    }

    public function test_a_refund_created_at_stripe_but_not_recorded_is_not_created_twice(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);

        $this->stripeRefunds->loseNextResponse = true;

        try {
            $this->processRefund($listingId);
            $this->fail('The lost Stripe response should surface so the job is retried.');
        } catch (ApiConnectionException) {
            $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'stripe_refund_id' => null]);
            $this->assertDatabaseHas('orders', ['id' => $this->sellerOrderId, 'refund_status' => null]);
        }

        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($listingId));

        $this->assertCount(1, $this->stripeRefunds->created);
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'stripe_refund_id' => 're_1']);
    }

    public function test_the_refund_waits_for_the_new_sales_fees_and_then_fetches_them_from_stripe(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);

        $this->assertSame(ResaleRefundOutcome::NOT_READY, $this->processRefund($listingId, attempt: 1));
        $this->assertSame(ResaleRefundOutcome::NOT_READY, $this->processRefund($listingId, attempt: 2));
        $this->assertCount(0, $this->stripeRefunds->created);

        $this->assertSame(ResaleRefundOutcome::SETTLED, $this->processRefund($listingId, attempt: 3));

        $this->assertSame([4750], array_column($this->stripeRefunds->created, 'amount'));
    }

    public function test_a_failed_refund_is_flagged_and_the_organizer_and_seller_are_told(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        $this->processRefund($listingId);
        Mail::fake();

        $this->stripeSends('re_1', 'failed');

        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::SOLD->name,
            'refund_status' => TicketResaleRefundStatus::FAILED->name,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $this->sellerOrderId, 'total_refunded' => 0]);
        $this->assertSellerNotified(TicketResaleNotice::REFUND_FAILED_FOR_SELLER, '$47.50');
        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::ORGANIZER_EMAIL)
            && ! $mail->isSentToBuyers());
    }

    public function test_a_refund_that_fails_after_stripe_first_reported_success_is_flagged(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        $this->processRefund($listingId);
        $this->stripeSends('re_1', 'succeeded');
        Mail::fake();

        $this->stripeSends('re_1', 'failed');

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::FAILED->name]);
        $this->assertSellerNotified(TicketResaleNotice::REFUND_FAILED_FOR_SELLER, '$47.50');
        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::ORGANIZER_EMAIL));
    }

    public function test_a_refund_that_never_starts_is_flagged_when_the_job_gives_up(): void
    {
        $listingId = $this->list();
        $this->pay($this->startCheckout('buyer'));
        Mail::fake();

        (new ProcessResaleRefundJob($listingId))->failed();

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::FAILED->name]);
        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::ORGANIZER_EMAIL));
    }

    public function test_a_refund_job_that_gives_up_after_the_refund_was_created_leaves_the_outcome_to_stripe(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        $this->processRefund($listingId);
        Mail::fake();

        (new ProcessResaleRefundJob($listingId))->failed();

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::PENDING->name]);
        Mail::assertNotQueued(TicketResaleNoticeMail::class);
    }

    public function test_a_refund_whose_stripe_update_never_arrives_is_settled_from_stripe_by_the_scheduled_command(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        $this->processRefund($listingId);

        $this->travel(30)->minutes();
        $this->artisan('ticket-resale:maintain')->assertSuccessful();
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::PENDING->name]);

        $this->travel(2)->hours();
        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::SUCCEEDED->name]);
        $this->assertDatabaseHas('orders', [
            'id' => $this->sellerOrderId,
            'refund_status' => OrderRefundStatus::PARTIALLY_REFUNDED->name,
            'total_refunded' => 47.50,
        ]);
        $this->assertSame(1, DB::table('order_refunds')->where('order_id', $this->sellerOrderId)->count());
    }

    public function test_a_refund_still_pending_at_stripe_stays_pending_and_is_asked_about_again_later(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        $this->processRefund($listingId);
        $this->stripeRefunds->statusAtStripe = 'pending';

        $this->travel(2)->hours();
        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::PENDING->name]);
        $this->assertDatabaseHas('orders', ['id' => $this->sellerOrderId, 'total_refunded' => 0]);
    }

    public function test_the_sellers_refund_is_capped_at_what_their_order_has_left_to_refund(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        DB::table('orders')->where('id', $this->sellerOrderId)->update([
            'total_refunded' => 80,
            'refund_status' => OrderRefundStatus::PARTIALLY_REFUNDED->name,
        ]);

        $this->pay($buyerOrderId);
        $this->recordFees($buyerOrderId, cardFee: 1.00, applicationFee: 1.50);
        $this->processRefund($listingId);

        $this->assertSame([2000], array_column($this->stripeRefunds->created, 'amount'));
        $this->assertDatabaseHas('attendees', ['id' => $this->sellerAttendeeId(), 'status' => AttendeeStatus::CANCELLED->name]);
        $this->assertSame(1, DB::table('attendees')->where('product_price_id', $this->priceId)->where('status', AttendeeStatus::ACTIVE->name)->where('order_id', '!=', $this->sellerOrderId)->count());
    }

    public function test_a_ticket_the_organizer_cancelled_during_checkout_sells_as_an_ordinary_ticket_with_no_refund(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');

        $this->organizerRequest('patch', "/events/{$this->eventId}/attendees/{$this->sellerAttendeeId()}", ['status' => AttendeeStatus::CANCELLED->name])
            ->assertOk();

        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::TICKET_CANCELLED->name,
        ]);
        $this->pay($buyerOrderId);

        $this->assertDatabaseHas('orders', ['id' => $buyerOrderId, 'status' => OrderStatus::COMPLETED->name]);
        $this->assertSame(2, (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold'));
        Queue::assertNotPushed(ProcessResaleRefundJob::class);
    }

    public function test_a_payment_that_arrives_after_the_checkout_expired_is_refunded_and_the_seller_keeps_the_ticket(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        DB::table('orders')->where('id', $buyerOrderId)->update(['reserved_until' => now()->subMinute()]);

        try {
            $this->pay($buyerOrderId);
            $this->fail('A payment for an expired checkout should be refunded.');
        } catch (PaymentRefundedException) {
            $this->assertSame('pi_buyer_'.$buyerOrderId, $this->stripeRefunds->created[0]->payment_intent);
        }

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'status' => TicketResaleListingStatus::LISTED->name]);
        $this->assertDatabaseHas('attendees', ['id' => $this->sellerAttendeeId(), 'status' => AttendeeStatus::ACTIVE->name]);
        Queue::assertNotPushed(ProcessResaleRefundJob::class);
    }

    public function test_a_checkout_that_expires_while_its_payment_is_being_accepted_still_completes_the_resale(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $guard = Mockery::mock(SeatedOrderCompletionGuard::class);
        $guard->shouldReceive('canRescueExpired')->andReturnFalse();
        $guard->shouldReceive('assertSeatsHeld')->andReturnUsing(fn () => $this->travel(1)->hours());
        $this->app->instance(SeatedOrderCompletionGuard::class, $guard);

        $this->pay($buyerOrderId);

        $this->assertDatabaseHas('orders', ['id' => $buyerOrderId, 'status' => OrderStatus::COMPLETED->name]);
        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::SOLD->name,
            'sold_order_id' => $buyerOrderId,
        ]);
        $this->assertDatabaseHas('attendees', ['id' => $this->sellerAttendeeId(), 'status' => AttendeeStatus::CANCELLED->name]);
        $this->assertSame(2, (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold'));
    }

    public function test_a_payment_after_the_organizer_moved_the_cutoff_past_the_hold_is_refunded(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['ticket_resale_cutoff_minutes' => 60 * 24 * 60]);

        try {
            $this->pay($buyerOrderId);
            $this->fail('A payment for a resale ticket past its cutoff should be refunded.');
        } catch (PaymentRefundedException) {
            $this->assertSame('pi_buyer_'.$buyerOrderId, $this->stripeRefunds->created[0]->payment_intent);
        }

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'status' => TicketResaleListingStatus::LISTED->name]);
        $this->assertDatabaseHas('attendees', ['id' => $this->sellerAttendeeId(), 'status' => AttendeeStatus::ACTIVE->name]);
        $this->assertDatabaseMissing('orders', ['id' => $buyerOrderId, 'status' => OrderStatus::COMPLETED->name]);
    }

    public function test_a_payment_after_the_seller_used_their_ticket_is_refunded_rather_than_selling_a_second_ticket(): void
    {
        $listingId = $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => now()->addHour()]);
        $publicId = DB::table('attendees')->where('id', $this->sellerAttendeeId())->value('public_id');
        $this->scan($publicId)->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'closed_reason' => TicketResaleCloseReason::TICKET_USED->name]);

        try {
            $this->pay($buyerOrderId);
            $this->fail('A payment for a resale ticket whose seller already used it should be refunded.');
        } catch (PaymentRefundedException) {
            $this->assertSame('pi_buyer_'.$buyerOrderId, $this->stripeRefunds->created[0]->payment_intent);
        }

        $this->assertDatabaseMissing('orders', ['id' => $buyerOrderId, 'status' => OrderStatus::COMPLETED->name]);
        $this->assertSame(2, (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold'));
    }

    public function test_an_order_holding_a_listed_ticket_cannot_pay_later_or_hand_payment_to_someone_else(): void
    {
        $this->list();
        $buyerOrderId = $this->startCheckout('buyer');
        $shortId = DB::table('orders')->where('id', $buyerOrderId)->value('short_id');

        $this->postJson("/public/events/{$this->eventId}/order/{$shortId}/await-offline-payment?session_identifier=buyer")
            ->assertStatus(409);
        $this->postJson("/public/events/{$this->eventId}/order/{$shortId}/payment-link?session_identifier=buyer")
            ->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $buyerOrderId, 'status' => OrderStatus::RESERVED->name, 'payment_link_created_at' => null]);
    }

    public function test_a_listed_ticket_is_refused_at_the_door_until_resale_closes(): void
    {
        $listingId = $this->list();
        $publicId = DB::table('attendees')->where('id', $this->sellerAttendeeId())->value('public_id');

        $this->scan($publicId)->assertJsonPath("errors.$publicId", fn (string $error) => str_contains($error, 'listed for resale'));
        $this->organizerRequest('post', "/events/{$this->eventId}/attendees/{$publicId}/check_in", ['action' => 'check_in'])
            ->assertJsonFragment(['message' => 'This ticket is listed for resale and cannot be checked in.']);
        $this->assertSame(0, DB::table('attendee_check_ins')->where('attendee_id', $this->sellerAttendeeId())->count());

        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => now()->addHour()]);

        $this->scan($publicId)->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::TICKET_USED->name,
        ]);
    }

    public function test_a_ticket_resold_while_it_is_being_scanned_is_refused_at_the_door(): void
    {
        $listingId = $this->list();
        $sellerAttendeeId = $this->sellerAttendeeId();
        $publicId = DB::table('attendees')->where('id', $sellerAttendeeId)->value('public_id');
        $this->resellWhenAttendeeIsFirstRead($sellerAttendeeId, $listingId);

        $this->scan($publicId)->assertJsonPath("errors.$publicId", fn (string $error) => str_contains($error, 'cancelled'));

        $this->assertSame(0, DB::table('attendee_check_ins')->where('attendee_id', $sellerAttendeeId)->count());
    }

    public function test_a_ticket_resold_while_the_organizer_checks_it_in_is_refused(): void
    {
        $listingId = $this->list();
        $sellerAttendeeId = $this->sellerAttendeeId();
        $publicId = DB::table('attendees')->where('id', $sellerAttendeeId)->value('public_id');
        $this->resellWhenAttendeeIsFirstRead($sellerAttendeeId, $listingId);

        $this->organizerRequest('post', "/events/{$this->eventId}/attendees/{$publicId}/check_in", ['action' => 'check_in'])
            ->assertStatus(409);

        $this->assertDatabaseHas('attendees', ['id' => $sellerAttendeeId, 'checked_in_at' => null]);
    }

    public function test_marking_the_ticket_type_not_resellable_releases_its_unheld_listings(): void
    {
        $listingId = $this->list();
        DB::table('products')->where('id', $this->productId)->update(['is_resellable' => false]);
        $publicId = DB::table('attendees')->where('id', $this->sellerAttendeeId())->value('public_id');

        $this->artisan('ticket-resale:maintain')->assertSuccessful();

        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::RESALE_TURNED_OFF->name,
        ]);
        $this->scan($publicId)->assertJsonCount(1, 'data');
    }

    public function test_a_ticket_held_by_a_buyers_checkout_stays_locked_at_the_door_even_after_resale_is_turned_off(): void
    {
        $this->list();
        $this->startCheckout('buyer');
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_ticket_resale' => false]);
        $publicId = DB::table('attendees')->where('id', $this->sellerAttendeeId())->value('public_id');

        $this->scan($publicId)->assertJsonPath("errors.$publicId", fn (string $error) => str_contains($error, 'listed for resale'));
    }

    public function test_cancelling_the_sellers_order_closes_its_listings(): void
    {
        $listingId = $this->list();
        Mail::fake();

        $this->organizerRequest('post', "/events/{$this->eventId}/orders/{$this->sellerOrderId}/cancel")->assertOk();

        $this->assertSellerNotified(TicketResaleNotice::CLOSED_FOR_SELLER);

        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::TICKET_CANCELLED->name,
        ]);
    }

    public function test_the_organizer_cannot_move_a_listed_ticket_to_another_type_while_a_buyer_holds_it(): void
    {
        $listingId = $this->list();
        $otherProductId = $this->insertProduct();
        $otherPriceId = $this->insertPrice($otherProductId, 5, ProductQuantityAppliesTo::EVENT->name, price: 50.00);
        $this->startCheckout('buyer');
        $change = [
            'first_name' => 'Sam',
            'last_name' => 'Seller',
            'email' => self::SELLER_EMAIL,
            'product_id' => $otherProductId,
            'product_price_id' => $otherPriceId,
        ];

        $this->organizerRequest('put', "/events/{$this->eventId}/attendees/{$this->sellerAttendeeId()}", $change)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id' => 'A buyer is checking out with this ticket right now']);
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'status' => TicketResaleListingStatus::LISTED->name]);

        DB::table('orders')->where('event_id', $this->eventId)->where('status', OrderStatus::RESERVED->name)->update(['reserved_until' => now()->subMinute()]);

        $this->organizerRequest('put', "/events/{$this->eventId}/attendees/{$this->sellerAttendeeId()}", $change)->assertOk();
        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::TICKET_TYPE_CHANGED->name,
        ]);
    }

    public function test_a_listed_ticket_cannot_be_moved_back_to_awaiting_payment(): void
    {
        $listingId = $this->list();

        $this->organizerRequest('patch', "/events/{$this->eventId}/attendees/{$this->sellerAttendeeId()}", ['status' => AttendeeStatus::AWAITING_PAYMENT->name])
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('attendees', ['id' => $this->sellerAttendeeId(), 'status' => AttendeeStatus::ACTIVE->name]);
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'status' => TicketResaleListingStatus::LISTED->name]);
    }

    public function test_a_resold_ticket_cannot_be_reactivated(): void
    {
        $this->list();
        $this->pay($this->startCheckout('buyer'));

        $this->organizerRequest('patch', "/events/{$this->eventId}/attendees/{$this->sellerAttendeeId()}", ['status' => AttendeeStatus::ACTIVE->name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('attendees', ['id' => $this->sellerAttendeeId(), 'status' => AttendeeStatus::CANCELLED->name]);
    }

    private function list(int $attendeeOffset = 0): int
    {
        return DB::table('ticket_resale_listings')->insertGetId([
            'event_id' => $this->eventId,
            'order_id' => $this->sellerOrderId,
            'attendee_id' => $this->sellerAttendeeId($attendeeOffset),
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'status' => TicketResaleListingStatus::LISTED->name,
            'min_unit_price' => 50.00,
            'estimated_refund' => 47.50,
            'connected_account_id' => self::STRIPE_ACCOUNT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sellerAttendeeId(int $offset = 0): int
    {
        return DB::table('attendees')->where('order_id', $this->sellerOrderId)->orderBy('id')->skip($offset)->value('id');
    }

    private function startCheckout(string $sessionIdentifier, int $quantity = 1): int
    {
        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            $route->flushController();
        }

        $shortId = $this->postJson("/public/events/{$this->eventId}/order?session_identifier={$sessionIdentifier}", [
            'products' => [[
                'product_id' => $this->productId,
                'event_occurrence_id' => $this->occurrenceId,
                'quantities' => [['price_id' => $this->priceId, 'quantity' => $quantity]],
            ]],
        ])->assertCreated()->json('data.short_id');

        $orderId = DB::table('orders')->where('short_id', $shortId)->value('id');

        DB::table('orders')->where('id', $orderId)->update([
            'email' => 'buyer@example.test',
            'first_name' => 'Bo',
            'last_name' => 'Buyer',
            'payment_status' => OrderPaymentStatus::AWAITING_PAYMENT->name,
        ]);
        for ($i = 0; $i < $quantity; $i++) {
            $this->insertAttendee($orderId, $this->productId, $this->priceId, $this->occurrenceId, AttendeeStatus::AWAITING_PAYMENT->name);
        }
        DB::table('stripe_payments')->insert([
            'order_id' => $orderId,
            'payment_intent_id' => 'pi_buyer_'.$orderId,
            'connected_account_id' => self::STRIPE_ACCOUNT,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $orderId;
    }

    private function pay(int $orderId, int $amount = 5000): void
    {
        $this->app->make(PaymentIntentSucceededHandler::class)->handleEvent(PaymentIntent::constructFrom([
            'id' => 'pi_buyer_'.$orderId,
            'object' => 'payment_intent',
            'amount' => $amount,
            'amount_received' => $amount,
            'currency' => 'usd',
            'latest_charge' => 'ch_buyer_'.$orderId,
            'payment_method' => 'pm_card',
            'application_fee_amount' => 150,
            'last_payment_error' => null,
        ]));
    }

    private function recordFees(int $orderId, float $cardFee, float $applicationFee): void
    {
        DB::table('order_payment_platform_fees')->insert([
            'order_id' => $orderId,
            'payment_platform' => PaymentProviders::STRIPE->value,
            'payment_platform_fee_amount' => $cardFee,
            'application_fee_gross_amount' => $applicationFee,
            'currency' => 'USD',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function processRefund(int $listingId, int $attempt = 1): ResaleRefundOutcome
    {
        return $this->app->make(ResaleRefundService::class)->process($listingId, $attempt);
    }

    private function stripeSends(string $refundId, string $status): void
    {
        $created = collect($this->stripeRefunds->created)->firstWhere('id', $refundId);

        $this->app->make(ChargeRefundUpdatedHandler::class)->handleEvent(
            Refund::constructFrom(array_merge($created->toArray(), ['status' => $status]))
        );
    }

    private function scan(string $publicId): TestResponse
    {
        return $this->postJson("/public/check-in-lists/{$this->checkInListShortId}/check-ins", [
            'attendees' => [['public_id' => $publicId, 'action' => 'check-in']],
        ]);
    }

    private function organizerRequest(string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return $this->json($method, $uri, $data, [
            'Authorization' => 'Bearer '.JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId)),
        ]);
    }

    private function resellWhenAttendeeIsFirstRead(int $attendeeId, int $listingId): void
    {
        $resold = false;

        DB::listen(function (QueryExecuted $query) use (&$resold, $attendeeId, $listingId): void {
            if ($resold || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'from "attendees"')) {
                return;
            }

            $resold = true;
            DB::table('attendees')->where('id', $attendeeId)->update(['status' => AttendeeStatus::CANCELLED->name]);
            DB::table('ticket_resale_listings')->where('id', $listingId)->update(['status' => TicketResaleListingStatus::SOLD->name]);
        });
    }

    private function recordAttendeeEvents(): Collection
    {
        $events = collect();
        $this->app['events']->listen(AttendeeEvent::class, function (AttendeeEvent $event) use ($events): void {
            $events->push([$event->type, $event->attendeeId]);
        });

        return $events;
    }

    private function assertSellerNotified(TicketResaleNotice $notice, ?string $amount = null): void
    {
        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::SELLER_EMAIL)
            && $mail->envelope()->subject === $notice->subject()
            && ($amount === null || str_contains($mail->content()->with['summary'], $amount)));
    }

    private function fakeStripeCharges(): ChargeService
    {
        return new class(Mockery::mock(StripeClient::class)) extends ChargeService
        {
            public function retrieve($id, $params = null, $opts = null)
            {
                return Charge::constructFrom([
                    'id' => $id,
                    'object' => 'charge',
                    'balance_transaction' => [
                        'id' => 'txn_'.$id,
                        'object' => 'balance_transaction',
                        'amount' => 5000,
                        'net' => 4750,
                        'fee' => 250,
                        'currency' => 'usd',
                        'fee_details' => [
                            ['type' => 'stripe_fee', 'amount' => 100, 'currency' => 'usd'],
                            ['type' => 'application_fee', 'amount' => 150, 'currency' => 'usd'],
                        ],
                    ],
                ]);
            }
        };
    }
}
