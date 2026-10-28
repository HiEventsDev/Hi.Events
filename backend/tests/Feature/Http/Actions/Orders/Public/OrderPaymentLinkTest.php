<?php

namespace Tests\Feature\Http\Actions\Orders\Public;

use Carbon\Carbon;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\SeatClaimStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Mail\Order\OrderPaymentLinkMail;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Order\PaymentLink\OrderPaymentLinkTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class OrderPaymentLinkTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const OWNER_SESSION = 'owner-session-identifier';

    private int $orderId;

    private string $orderShortId;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'payment_providers' => json_encode(['STRIPE', 'OFFLINE']),
            'offline_payment_instructions' => '<p>Pay by bank transfer</p>',
            'payment_links_enabled' => true,
            'payment_link_expiry_hours' => 72,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);

        $this->orderId = $this->insertCheckoutOrder();
        $this->orderShortId = DB::table('orders')->where('id', $this->orderId)->value('short_id');
    }

    public function test_the_buyer_can_create_a_payment_link_that_holds_their_places(): void
    {
        Mail::fake();

        $response = $this->postAsOwner($this->linkUrl())->assertOk();

        $order = DB::table('orders')->where('id', $this->orderId)->first();
        $this->assertNotNull($order->payment_link_created_at);
        $this->assertEqualsWithDelta(
            now()->addHours(72)->getTimestamp(),
            Carbon::parse($order->reserved_until, 'UTC')->getTimestamp(),
            5,
        );
        $this->assertSame($this->expectedUrl(), $response->json('data.payment_link_url'));
        $response->assertJsonPath('data.is_payment_link_payer', false);

        Mail::assertQueued(OrderPaymentLinkMail::class, function (OrderPaymentLinkMail $mail) {
            $this->assertStringContainsString(e($this->expectedUrl()), $mail->render());

            return $mail->hasTo('buyer@example.test');
        });
    }

    public function test_creating_the_link_again_returns_the_same_link_without_extending_the_hold(): void
    {
        Mail::fake();

        $first = $this->postAsOwner($this->linkUrl())->assertOk();
        $heldUntil = DB::table('orders')->where('id', $this->orderId)->value('reserved_until');

        $this->travel(2)->hours();

        $second = $this->postAsOwner($this->linkUrl())->assertOk();

        $this->assertSame($first->json('data.payment_link_url'), $second->json('data.payment_link_url'));
        $this->assertSame($heldUntil, DB::table('orders')->where('id', $this->orderId)->value('reserved_until'));
        Mail::assertQueued(OrderPaymentLinkMail::class, 1);
    }

    public function test_the_hold_never_runs_past_the_start_of_the_event(): void
    {
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update([
            'start_date' => now()->addHours(5)->toDateTimeString(),
        ]);

        $this->postAsOwner($this->linkUrl())->assertOk();

        $this->assertEqualsWithDelta(
            now()->addHours(5)->getTimestamp(),
            Carbon::parse(DB::table('orders')->where('id', $this->orderId)->value('reserved_until'), 'UTC')->getTimestamp(),
            5,
        );
    }

    public function test_a_waitlist_offer_order_is_never_held_past_the_offer_expiry(): void
    {
        $offerExpiresAt = now()->addMinutes(30)->startOfSecond();
        DB::table('orders')->where('id', $this->orderId)->update(['reserved_until' => $offerExpiresAt]);
        DB::table('waitlist_entries')->insert([
            'event_id' => $this->eventId,
            'product_price_id' => $this->priceId,
            'email' => 'buyer@example.test',
            'first_name' => 'Ray',
            'status' => WaitlistEntryStatus::OFFERED->name,
            'order_id' => $this->orderId,
            'offer_expires_at' => $offerExpiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postAsOwner($this->linkUrl())->assertOk();

        $this->assertSame(
            $offerExpiresAt->getTimestamp(),
            Carbon::parse(DB::table('orders')->where('id', $this->orderId)->value('reserved_until'), 'UTC')->getTimestamp(),
        );
    }

    public function test_the_hold_is_never_shortened(): void
    {
        $longHold = now()->addDays(5)->startOfSecond();
        DB::table('orders')->where('id', $this->orderId)->update(['reserved_until' => $longHold]);

        $this->postAsOwner($this->linkUrl())->assertOk();

        $this->assertSame(
            $longHold->getTimestamp(),
            Carbon::parse(DB::table('orders')->where('id', $this->orderId)->value('reserved_until'), 'UTC')->getTimestamp(),
        );
    }

    public function test_only_the_buyer_who_started_the_order_can_create_a_link(): void
    {
        $this->postJson($this->linkUrl().'?session_identifier=someone-else')->assertUnauthorized();
        $this->postJson($this->linkUrl())->assertUnauthorized();

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('payment_link_created_at'));
    }

    public function test_the_person_paying_cannot_create_a_new_link(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => now()->startOfSecond()]);

        $this->postJson($this->linkUrl().'?session_identifier='.$this->token())->assertUnauthorized();
    }

    public function test_links_at_the_payment_step_need_both_offline_and_online_payments(): void
    {
        Mail::fake();

        DB::table('event_settings')->where('event_id', $this->eventId)->update(['payment_links_enabled' => false]);
        $this->postAsOwner($this->linkUrl())->assertStatus(409);

        DB::table('event_settings')->where('event_id', $this->eventId)->update([
            'payment_links_enabled' => true,
            'payment_providers' => json_encode(['STRIPE']),
        ]);
        $this->postAsOwner($this->linkUrl())->assertStatus(409);

        DB::table('event_settings')->where('event_id', $this->eventId)->update([
            'payment_providers' => json_encode(['OFFLINE']),
        ]);
        $this->postAsOwner($this->linkUrl())->assertStatus(409);

        Mail::assertNothingQueued();
    }

    public function test_a_link_needs_completed_details_a_balance_and_a_live_reservation(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['email' => null]);
        $this->postAsOwner($this->linkUrl())->assertStatus(409);

        DB::table('orders')->where('id', $this->orderId)->update(['email' => 'buyer@example.test', 'total_gross' => 0]);
        $this->postAsOwner($this->linkUrl())->assertStatus(409);

        DB::table('orders')->where('id', $this->orderId)->update(['total_gross' => 20, 'reserved_until' => now()->subMinute()]);
        $this->postAsOwner($this->linkUrl())->assertStatus(409);
    }

    public function test_the_order_must_belong_to_the_event_in_the_url(): void
    {
        $otherEventId = $this->insertEvent();

        $this->postJson("/public/events/{$otherEventId}/order/{$this->orderShortId}/payment-link?session_identifier=".self::OWNER_SESSION)
            ->assertNotFound();
    }

    public function test_the_link_lets_the_person_paying_view_the_order(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();

        $this->getJson($this->orderUrl().'?session_identifier='.$this->token())
            ->assertOk()
            ->assertJsonPath('data.is_payment_link_payer', true)
            ->assertJsonPath('data.payment_link_url', $this->expectedUrl());
    }

    public function test_a_wrong_or_tampered_token_is_rejected(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();
        $token = $this->token();

        $this->getJson($this->orderUrl().'?session_identifier='.str_repeat('a', 64))->assertForbidden();
        $this->getJson($this->orderUrl().'?session_identifier='.substr($token, 0, -1).'0')->assertForbidden();
        $this->getJson($this->orderUrl().'?session_identifier='.strtoupper($token))->assertForbidden();
    }

    public function test_a_token_only_grants_access_to_its_own_order(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();
        $token = $this->token();

        $otherOrderId = $this->insertCheckoutOrder();
        $otherShortId = DB::table('orders')->where('id', $otherOrderId)->value('short_id');
        DB::table('orders')->where('id', $otherOrderId)->update(['payment_link_created_at' => now()->startOfSecond()]);

        $this->getJson("/public/events/{$this->eventId}/order/{$otherShortId}?session_identifier={$token}")
            ->assertForbidden();
    }

    public function test_an_order_without_a_link_cannot_be_reached_with_a_token(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => now()->startOfSecond()]);
        $guessed = $this->token();
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => null]);

        $this->getJson($this->orderUrl().'?session_identifier='.$guessed)->assertForbidden();
    }

    public function test_the_person_paying_can_choose_offline_payment(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();

        $this->postJson($this->orderUrl().'/await-offline-payment?session_identifier='.$this->token())
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::AWAITING_OFFLINE_PAYMENT->name);
    }

    public function test_the_payment_link_url_is_not_exposed_once_the_order_is_complete(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();
        DB::table('orders')->where('id', $this->orderId)->update(['status' => OrderStatus::COMPLETED->name]);

        $this->getJson($this->orderUrl().'?session_identifier='.$this->token())
            ->assertOk()
            ->assertJsonMissingPath('data.payment_link_url');
    }

    public function test_a_placed_pay_later_order_can_be_shared_without_a_hold_or_an_email(): void
    {
        Mail::fake();
        $this->placeAsPayLater();
        $heldUntil = DB::table('orders')->where('id', $this->orderId)->value('reserved_until');

        $response = $this->postJson($this->linkUrl())->assertOk();

        $this->assertNotNull(DB::table('orders')->where('id', $this->orderId)->value('payment_link_created_at'));
        $this->assertSame($heldUntil, DB::table('orders')->where('id', $this->orderId)->value('reserved_until'));
        $this->assertSame($this->expectedUrl(), $response->json('data.payment_link_url'));
        $this->assertStringContainsString("/checkout/{$this->eventId}/{$this->orderShortId}/summary?session_identifier=", $this->expectedUrl());
        Mail::assertNothingQueued();
    }

    public function test_the_person_paying_a_placed_order_is_recognised_by_their_link(): void
    {
        $this->placeAsPayLater();
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => now()->startOfSecond()]);

        $this->getJson($this->orderUrl().'?session_identifier='.$this->token())
            ->assertOk()
            ->assertJsonPath('data.is_payment_link_payer', true)
            ->assertJsonPath('data.payment_link_url', $this->expectedUrl());
    }

    public function test_the_person_paying_still_gets_the_payer_view_once_the_order_is_paid(): void
    {
        $this->placeAsPayLater();
        DB::table('orders')->where('id', $this->orderId)->update([
            'payment_link_created_at' => now()->startOfSecond(),
            'status' => OrderStatus::COMPLETED->name,
        ]);

        $this->getJson($this->orderUrl().'?session_identifier='.$this->token())
            ->assertOk()
            ->assertJsonPath('data.is_payment_link_payer', true)
            ->assertJsonMissingPath('data.payment_link_url');
    }

    public function test_opening_a_payment_link_does_not_replace_the_payers_own_checkout_session(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => now()->startOfSecond()]);

        $this->getJson($this->orderUrl().'?session_identifier='.$this->token())
            ->assertOk()
            ->assertJsonPath('data.is_payment_link_payer', true)
            ->assertCookieMissing('session_identifier');
    }

    public function test_the_buyer_returning_with_their_own_session_still_gets_the_session_cookie(): void
    {
        $this->getJson($this->orderUrl().'?session_identifier='.self::OWNER_SESSION)
            ->assertOk()
            ->assertCookie('session_identifier');
    }

    public function test_the_person_paying_cannot_pay_again_once_the_order_is_placed(): void
    {
        $this->placeAsPayLater();
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => now()->startOfSecond()]);

        $this->postJson($this->orderUrl().'/await-offline-payment?session_identifier='.$this->token())
            ->assertStatus(409);

        $this->assertSame(OrderStatus::AWAITING_OFFLINE_PAYMENT->name, DB::table('orders')->where('id', $this->orderId)->value('status'));
    }

    public function test_the_person_paying_cannot_start_a_card_payment_once_the_order_is_placed(): void
    {
        $this->placeAsPayLater();
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => now()->startOfSecond()]);

        $response = $this->postJson($this->orderUrl().'/stripe/payment_intent?session_identifier='.$this->token());

        $this->assertFalse($response->isSuccessful());
        $this->assertSame(0, DB::table('stripe_payments')->where('order_id', $this->orderId)->count());
    }

    public function test_viewing_a_placed_order_without_the_link_is_not_the_person_paying(): void
    {
        $this->placeAsPayLater();
        DB::table('orders')->where('id', $this->orderId)->update(['payment_link_created_at' => now()->startOfSecond()]);

        $this->getJson($this->orderUrl().'?session_identifier=not-the-token')
            ->assertOk()
            ->assertJsonPath('data.is_payment_link_payer', false);
    }

    public function test_sharing_a_placed_order_needs_payment_links_enabled(): void
    {
        $this->placeAsPayLater();
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['payment_links_enabled' => false]);

        $this->postJson($this->linkUrl())->assertStatus(409);

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('payment_link_created_at'));
    }

    public function test_a_completed_order_cannot_get_a_payment_link(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['status' => OrderStatus::COMPLETED->name]);

        $this->postAsOwner($this->linkUrl())->assertStatus(409);
    }

    public function test_an_order_with_a_payment_link_cannot_be_abandoned(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();

        $this->postJson($this->orderUrl().'/abandon?session_identifier='.self::OWNER_SESSION)->assertStatus(409);
        $this->postJson($this->orderUrl().'/abandon?session_identifier='.$this->token())->assertStatus(409);

        $this->assertSame(OrderStatus::RESERVED->name, DB::table('orders')->where('id', $this->orderId)->value('status'));
    }

    public function test_the_person_paying_cannot_edit_the_registration(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();

        $response = $this->putJson($this->orderUrl().'?session_identifier='.$this->token(), [
            'order' => ['first_name' => 'Mallory', 'last_name' => 'Payer', 'email' => 'mallory@example.test'],
            'products' => [],
        ]);

        $this->assertFalse($response->isSuccessful());

        $this->assertSame('buyer@example.test', DB::table('orders')->where('id', $this->orderId)->value('email'));
    }

    public function test_starting_another_order_does_not_discard_an_order_waiting_on_a_payment_link(): void
    {
        $this->postAsOwner($this->linkUrl())->assertOk();
        $plainReservationId = $this->insertCheckoutOrder();

        app(OrderManagementService::class)->deleteExistingOrders($this->eventId, self::OWNER_SESSION);

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('deleted_at'));
        $this->assertNotNull(DB::table('orders')->where('id', $plainReservationId)->value('deleted_at'));
    }

    public function test_a_payment_link_keeps_the_seats_held_until_it_expires(): void
    {
        DB::table('seat_claims')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => 'e1.0.0',
            'is_zone' => false,
            'band_key' => 'b_standard',
            'seat_label' => 'A-1',
            'order_id' => $this->orderId,
            'held_back' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postAsOwner($this->linkUrl())->assertOk();
        $seats = app(SeatClaimRepositoryInterface::class);

        $this->travel(48)->hours();
        $this->assertSame(
            SeatClaimStatus::HELD->name,
            $seats->findLiveWithHoldersForOccurrence($this->occurrenceId)->firstWhere('seat_uid', 'e1.0.0')?->status,
        );

        $this->travel(25)->hours();
        $this->assertNull($seats->findLiveWithHoldersForOccurrence($this->occurrenceId)->firstWhere('seat_uid', 'e1.0.0'));
    }

    private function insertCheckoutOrder(): int
    {
        $orderId = $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 2);
        DB::table('orders')->where('id', $orderId)->update([
            'session_id' => self::OWNER_SESSION,
            'first_name' => 'Ray',
            'last_name' => 'Buyer',
            'email' => 'buyer@example.test',
            'total_gross' => 20,
            'locale' => 'en',
        ]);

        return $orderId;
    }

    private function placeAsPayLater(): void
    {
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['payment_providers' => json_encode(['OFFLINE'])]);
        DB::table('orders')->where('id', $this->orderId)->update([
            'status' => OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            'payment_provider' => 'OFFLINE',
        ]);
    }

    private function postAsOwner(string $url): TestResponse
    {
        return $this->postJson($url.'?session_identifier='.self::OWNER_SESSION);
    }

    private function orderUrl(): string
    {
        return "/public/events/{$this->eventId}/order/{$this->orderShortId}";
    }

    private function linkUrl(): string
    {
        return $this->orderUrl().'/payment-link';
    }

    private function expectedUrl(): string
    {
        return app(OrderPaymentLinkTokenService::class)->urlFor($this->freshOrder());
    }

    private function token(): string
    {
        parse_str((string) parse_url($this->expectedUrl(), PHP_URL_QUERY), $query);

        return $query['session_identifier'];
    }

    private function freshOrder(): OrderDomainObject
    {
        return app(OrderRepositoryInterface::class)->findById($this->orderId);
    }
}
