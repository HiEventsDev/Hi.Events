<?php

namespace Tests\Feature\Http\Actions\TicketResale;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\Jobs\TicketResale\ProcessResaleRefundJob;
use HiEvents\Mail\TicketResale\TicketResaleNoticeMail;
use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Feature\Support\SeedsResaleListings;
use Tests\TestCase;

class OrganizerResaleListingsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use SeedsResaleListings;

    private const MONEY_KEYS = ['currency', 'estimated_refund', 'refund_amount', 'refund_status'];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ProcessResaleRefundJob::class]);

        $this->seedResaleEvent();
    }

    public function test_the_list_shows_each_listing_with_its_state_newest_first(): void
    {
        $openId = $this->insertListing(0, ['created_at' => now()->subMinutes(4)]);
        $heldId = $this->insertListing(1, [
            'claimed_by_order_id' => $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1),
            'created_at' => now()->subMinutes(3),
        ]);
        $replacementOrderId = $this->sellTickets($this->productId, $this->priceId, $this->occurrenceId, 1);
        $soldId = $this->insertListing(2, [
            'status' => TicketResaleListingStatus::SOLD->name,
            'sold_order_id' => $replacementOrderId,
            'refund_status' => TicketResaleRefundStatus::FAILED->name,
            'refund_amount' => 47.50,
            'created_at' => now()->subMinutes(2),
        ]);
        $closedId = $this->insertListing(3, [
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::WITHDRAWN->value,
            'created_at' => now()->subMinute(),
        ]);
        $this->insertListingOnAnotherEvent();

        $listings = $this->getJson("/events/{$this->eventId}/resale-listings", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->json('data');

        $this->assertSame([$closedId, $soldId, $heldId, $openId], array_column($listings, 'id'));
        $this->assertSame(['CLOSED', 'SOLD', 'HELD', 'OPEN'], array_column($listings, 'state'));
        $this->assertSame([false, true, false, false], array_column($listings, 'needs_attention'));

        [$closed, $sold, $held, $open] = $listings;
        $seller = DB::table('orders')->where('id', $this->sellerOrderId)->first();

        $this->assertSame(TicketResaleCloseReason::WITHDRAWN->value, $closed['closed_reason']);
        $this->assertSame(
            ['id' => $replacementOrderId, 'public_id' => DB::table('orders')->where('id', $replacementOrderId)->value('public_id')],
            $sold['sold_order'],
        );
        $this->assertSame(TicketResaleRefundStatus::FAILED->name, $sold['refund_status']);
        $this->assertEquals(47.50, $sold['refund_amount']);
        $this->assertNotNull($held['held_until']);
        $this->assertNull($open['held_until']);
        $this->assertNull($open['sold_order']);
        $this->assertSame(['id' => $seller->id, 'public_id' => $seller->public_id, 'short_id' => $seller->short_id], $open['order']);
        $this->assertSame($this->sellerAttendeeId(), $open['attendee']['id']);
        $this->assertSame(DB::table('attendees')->where('id', $this->sellerAttendeeId())->value('email'), $open['attendee']['email']);
        $this->assertSame(DB::table('products')->where('id', $this->productId)->value('title'), $open['product_title']);
        $this->assertSame('Early bird', $open['price_label']);
        $this->assertSame($this->occurrenceId, $open['event_occurrence_id']);
        $this->assertSame('USD', $open['currency']);
        $this->assertEquals(47.50, $open['estimated_refund']);
    }

    public function test_a_listing_that_is_no_longer_offered_or_awaits_withdrawal_says_so(): void
    {
        $this->insertListing(0);
        $this->insertListing(1, [
            'claimed_by_order_id' => $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1),
            'withdrawal_requested_at' => now(),
        ]);
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_ticket_resale' => false]);

        $states = $this->getJson("/events/{$this->eventId}/resale-listings", $this->ownerHeaders())->assertOk()->json('data.*.state');

        $this->assertEqualsCanonicalizing(['NOT_OFFERED', 'WITHDRAWAL_REQUESTED'], $states);
    }

    public function test_the_list_can_be_filtered_by_status_and_paged(): void
    {
        $this->insertListing(0);
        $soldId = $this->insertListing(1, ['status' => TicketResaleListingStatus::SOLD->name]);
        $this->insertListing(2, ['status' => TicketResaleListingStatus::CLOSED->name]);

        $this->getJson("/events/{$this->eventId}/resale-listings?filter_fields[status][eq]=SOLD", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $soldId);

        $this->getJson("/events/{$this->eventId}/resale-listings?per_page=2&page=2", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_a_member_without_financials_sees_listings_without_money_or_the_buyer_order_key(): void
    {
        $this->insertListing(0, ['status' => TicketResaleListingStatus::SOLD->name, 'refund_status' => TicketResaleRefundStatus::FAILED->name]);
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);

        $listing = $this->getJson("/events/{$this->eventId}/resale-listings", $this->headersFor($member, $this->accountId))
            ->assertOk()
            ->json('data.0');

        foreach (self::MONEY_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $listing);
        }
        $this->assertArrayNotHasKey('short_id', $listing['order']);
        $this->assertTrue($listing['needs_attention']);
        $this->assertSame('SOLD', $listing['state']);
    }

    public function test_the_organizer_closes_an_open_listing_and_the_seller_is_told(): void
    {
        $listingId = $this->insertListing();
        Mail::fake();

        $this->postJson("/events/{$this->eventId}/resale-listings/{$listingId}/close", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $listingId)
            ->assertJsonPath('data.status', TicketResaleListingStatus::CLOSED->name)
            ->assertJsonPath('data.state', 'CLOSED')
            ->assertJsonPath('data.closed_reason', TicketResaleCloseReason::CLOSED_BY_ORGANIZER->value);

        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => TicketResaleCloseReason::CLOSED_BY_ORGANIZER->value,
        ]);
        $this->assertDatabaseHas('order_audit_logs', [
            'attendee_id' => $this->sellerAttendeeId(),
            'order_id' => $this->sellerOrderId,
            'action' => OrderAuditAction::TICKET_RESALE_CLOSED->value,
        ]);
        Mail::assertQueued(TicketResaleNoticeMail::class, fn (TicketResaleNoticeMail $mail) => $mail->hasTo(self::SELLER_EMAIL)
            && $mail->envelope()->subject === TicketResaleNotice::CLOSED_FOR_SELLER->subject());
    }

    public function test_a_listing_cannot_be_closed_while_a_buyer_holds_it_or_once_it_is_no_longer_listed(): void
    {
        $heldId = $this->insertListing(0, [
            'claimed_by_order_id' => $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1),
        ]);
        $soldId = $this->insertListing(1, ['status' => TicketResaleListingStatus::SOLD->name]);

        $this->postJson("/events/{$this->eventId}/resale-listings/{$heldId}/close", [], $this->ownerHeaders())
            ->assertStatus(409)
            ->assertJsonPath('message', 'A buyer is checking out with this ticket right now. Try again in a few minutes.');
        $this->postJson("/events/{$this->eventId}/resale-listings/{$soldId}/close", [], $this->ownerHeaders())
            ->assertStatus(409);

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $heldId, 'status' => TicketResaleListingStatus::LISTED->name]);
        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $soldId, 'status' => TicketResaleListingStatus::SOLD->name]);
    }

    public function test_another_events_listing_cannot_be_closed_or_retried_through_this_event(): void
    {
        $otherListingId = $this->insertListingOnAnotherEvent();
        DB::table('ticket_resale_listings')->where('id', $otherListingId)->update([
            'status' => TicketResaleListingStatus::LISTED->name,
        ]);

        $this->postJson("/events/{$this->eventId}/resale-listings/{$otherListingId}/close", [], $this->ownerHeaders())->assertNotFound();
        $this->postJson("/events/{$this->eventId}/resale-listings/{$otherListingId}/retry-refund", [], $this->ownerHeaders())->assertNotFound();

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $otherListingId, 'status' => TicketResaleListingStatus::LISTED->name]);
    }

    public function test_a_read_only_member_cannot_close_a_listing(): void
    {
        $listingId = $this->insertListing();
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->postJson("/events/{$this->eventId}/resale-listings/{$listingId}/close", [], $this->headersFor($viewer, $this->accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'READ_ONLY_ROLE');

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'status' => TicketResaleListingStatus::LISTED->name]);
    }

    public function test_a_member_who_can_refund_retries_a_failed_refund(): void
    {
        $listingId = $this->insertFailedRefund();

        $this->postJson("/events/{$this->eventId}/resale-listings/{$listingId}/retry-refund", [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $listingId)
            ->assertJsonPath('data.needs_attention', false)
            ->assertJsonPath('data.refund_status', TicketResaleRefundStatus::PENDING->name);

        $this->assertDatabaseHas('ticket_resale_listings', [
            'id' => $listingId,
            'status' => TicketResaleListingStatus::SOLD->name,
            'refund_status' => TicketResaleRefundStatus::PENDING->name,
            'stripe_refund_id' => null,
            'refund_attempts' => 1,
        ]);
        $this->assertDatabaseHas('order_audit_logs', [
            'attendee_id' => $this->sellerAttendeeId(),
            'action' => OrderAuditAction::TICKET_RESALE_REFUND_RETRIED->value,
        ]);
        Queue::assertPushed(ProcessResaleRefundJob::class, 1);
    }

    public function test_a_member_without_the_refund_permission_cannot_retry_a_refund(): void
    {
        $listingId = $this->insertFailedRefund();
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($member, $this->accountId, ['ISSUE_REFUNDS' => false]);

        $this->postJson("/events/{$this->eventId}/resale-listings/{$listingId}/retry-refund", [], $this->headersFor($member, $this->accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $listingId, 'refund_status' => TicketResaleRefundStatus::FAILED->name]);
        Queue::assertNotPushed(ProcessResaleRefundJob::class);
    }

    public function test_a_refund_that_has_not_failed_cannot_be_retried(): void
    {
        $pendingId = $this->insertListing(0, [
            'status' => TicketResaleListingStatus::SOLD->name,
            'refund_status' => TicketResaleRefundStatus::PENDING->name,
        ]);
        $listedId = $this->insertListing(1);

        $this->postJson("/events/{$this->eventId}/resale-listings/{$pendingId}/retry-refund", [], $this->ownerHeaders())->assertStatus(409);
        $this->postJson("/events/{$this->eventId}/resale-listings/{$listedId}/retry-refund", [], $this->ownerHeaders())->assertStatus(409);

        $this->assertDatabaseHas('ticket_resale_listings', ['id' => $pendingId, 'refund_attempts' => 0]);
        Queue::assertNotPushed(ProcessResaleRefundJob::class);
    }

    public function test_the_organizer_attendee_list_and_detail_show_whether_a_ticket_is_listed_or_resold(): void
    {
        $listedAttendeeId = $this->sellerAttendeeId(0);
        $withdrawnAttendeeId = $this->sellerAttendeeId(1);
        $resoldAttendeeId = $this->sellerAttendeeId(2);
        $untouchedAttendeeId = $this->sellerAttendeeId(3);
        $replacementOrderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->insertListing(0);
        $this->insertListing(1, ['withdrawal_requested_at' => now()]);
        $this->insertListing(2, ['status' => TicketResaleListingStatus::SOLD->name, 'sold_order_id' => $replacementOrderId]);
        $resold = [
            'status' => 'SOLD',
            'sold_order_id' => $replacementOrderId,
            'sold_order_public_id' => DB::table('orders')->where('id', $replacementOrderId)->value('public_id'),
        ];
        $none = ['status' => null, 'sold_order_id' => null, 'sold_order_public_id' => null];

        $listed = collect($this->getJson("/events/{$this->eventId}/attendees", $this->ownerHeaders())->assertOk()->json('data'))
            ->pluck('resale', 'id');

        $this->assertSame(['status' => 'LISTED', 'sold_order_id' => null, 'sold_order_public_id' => null], $listed[$listedAttendeeId]);
        $this->assertSame($none, $listed[$withdrawnAttendeeId]);
        $this->assertSame($resold, $listed[$resoldAttendeeId]);
        $this->assertSame($none, $listed[$untouchedAttendeeId]);

        $this->getJson("/events/{$this->eventId}/attendees/{$listedAttendeeId}", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.resale.status', 'LISTED');
        $this->getJson("/events/{$this->eventId}/attendees/{$withdrawnAttendeeId}", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.resale', $none);
        $this->getJson("/events/{$this->eventId}/attendees/{$resoldAttendeeId}", $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.resale', $resold);
    }

    public function test_the_resold_webhook_names_the_replacement_order(): void
    {
        $replacementOrderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->insertListing(0, ['status' => TicketResaleListingStatus::SOLD->name, 'sold_order_id' => $replacementOrderId]);
        DB::table('webhooks')->insert([
            'url' => 'http://127.0.0.1/hook',
            'event_types' => json_encode([DomainEventType::ATTENDEE_RESOLD->value]),
            'status' => 'ENABLED',
            'secret' => 'test-secret',
            'user_id' => $this->userId,
            'account_id' => $this->accountId,
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Config::set('app.allowed_internal_webhook_hosts', '127.0.0.1');
        $sentRequests = [];
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], 'ok')]));
        $handler->push(Middleware::history($sentRequests));
        $this->app->instance(Client::class, new Client(['handler' => $handler]));

        $this->app->make(WebhookDispatchService::class)->dispatchAttendeeWebhook(DomainEventType::ATTENDEE_RESOLD, $this->sellerAttendeeId());

        $this->assertCount(1, $sentRequests);
        $body = json_decode((string) $sentRequests[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(DomainEventType::ATTENDEE_RESOLD->value, $body['event_type']);
        $this->assertSame([
            'status' => 'SOLD',
            'sold_order_id' => $replacementOrderId,
            'sold_order_public_id' => DB::table('orders')->where('id', $replacementOrderId)->value('public_id'),
        ], $body['payload']['resale']);
    }

    public function test_the_public_order_says_when_its_checkout_holds_a_listed_ticket(): void
    {
        $this->insertListing();
        $otherProductId = $this->insertProduct();
        $otherPriceId = $this->insertPrice($otherProductId, 5, price: 50.00);

        $holding = $this->startCheckout('holder', $this->productId, $this->priceId);
        $this->assertTrue($holding['holds_resale_listing']);
        $this->assertTrue($this->publicOrder($holding['short_id'], 'holder')['holds_resale_listing']);

        $plain = $this->startCheckout('plain', $otherProductId, $otherPriceId);
        $this->assertFalse($plain['holds_resale_listing']);
        $this->assertFalse($this->publicOrder($plain['short_id'], 'plain')['holds_resale_listing']);

        $sellerShortId = DB::table('orders')->where('id', $this->sellerOrderId)->value('short_id');
        $this->assertFalse($this->publicOrder($sellerShortId, 'seller')['holds_resale_listing']);
    }

    private function insertFailedRefund(): int
    {
        return $this->insertListing(0, [
            'status' => TicketResaleListingStatus::SOLD->name,
            'sold_order_id' => $this->insertOrder(OrderStatus::COMPLETED->name),
            'refund_status' => TicketResaleRefundStatus::FAILED->name,
            'refund_amount' => 47.50,
            'stripe_refund_id' => 're_failed',
        ]);
    }

    private function startCheckout(string $sessionIdentifier, int $productId, int $priceId): array
    {
        $this->forgetCheckoutSession();

        return $this->postJson("/public/events/{$this->eventId}/order?session_identifier={$sessionIdentifier}", [
            'products' => [[
                'product_id' => $productId,
                'event_occurrence_id' => $this->occurrenceId,
                'quantities' => [['price_id' => $priceId, 'quantity' => 1]],
            ]],
        ])->assertCreated()->json('data');
    }

    private function publicOrder(string $shortId, string $sessionIdentifier): array
    {
        $this->forgetCheckoutSession();

        return $this->getJson("/public/events/{$this->eventId}/order/{$shortId}?session_identifier={$sessionIdentifier}")
            ->assertOk()
            ->json('data');
    }

    private function forgetCheckoutSession(): void
    {
        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            $route->flushController();
        }
    }

    private function ownerHeaders(): array
    {
        return $this->headersFor(User::find($this->userId), $this->accountId);
    }
}
