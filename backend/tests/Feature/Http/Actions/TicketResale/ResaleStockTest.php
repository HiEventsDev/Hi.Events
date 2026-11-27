<?php

namespace Tests\Feature\Http\Actions\TicketResale;

use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Jobs\Waitlist\SendWaitlistOfferEmailJob;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\TicketResale\ResaleOccurrenceAvailabilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class ResaleStockTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $sellerOrderId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'allow_ticket_resale' => true,
            'ticket_resale_cutoff_minutes' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->occurrenceId = $this->insertOccurrence(daysAhead: 30);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, 2, ProductQuantityAppliesTo::EVENT->name, quantitySold: 2);
        $this->sellerOrderId = $this->sellTickets($this->productId, $this->priceId, $this->occurrenceId, 2);
    }

    public function test_a_sold_out_ticket_type_with_a_listing_has_one_place_for_public_checkout_only(): void
    {
        $this->list();

        $this->assertSame(1, $this->available());
        $this->assertSame(0, $this->available(includeResaleStock: false));
        $this->assertSame(1, $this->eventWideAvailable());
    }

    public function test_a_buyer_holds_the_listing_during_checkout_and_the_next_buyer_finds_it_sold_out(): void
    {
        $listingId = $this->list();

        $orderId = $this->orderId($this->postOrder('first-buyer')->assertCreated()->json('data.short_id'));

        $this->assertSame($orderId, DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
        $this->assertSame(0, $this->available());
        $this->assertSame(0, $this->available(includeResaleStock: false));
        $this->postOrder('second-buyer')->assertUnprocessable();
    }

    public function test_an_abandoned_or_expired_checkout_frees_the_listing_for_the_next_buyer(): void
    {
        $listingId = $this->list();
        $firstOrderId = $this->orderId($this->postOrder('first-buyer')->assertCreated()->json('data.short_id'));

        DB::table('orders')->where('id', $firstOrderId)->update(['reserved_until' => now()->subMinute()]);

        $this->assertSame(1, $this->available());
        $secondOrderId = $this->orderId($this->postOrder('second-buyer')->assertCreated()->json('data.short_id'));
        $this->assertSame($secondOrderId, DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));

        DB::table('orders')->where('id', $secondOrderId)->update(['status' => OrderStatus::ABANDONED->name]);
        $this->assertSame(1, $this->available());

        DB::table('orders')->where('id', $secondOrderId)->update(['status' => OrderStatus::RESERVED->name, 'deleted_at' => now()]);
        $this->assertSame(1, $this->available());
    }

    public function test_unsold_tickets_sell_before_a_listed_one(): void
    {
        DB::table('product_prices')->where('id', $this->priceId)->update(['initial_quantity_available' => 5]);
        $listingId = $this->list();

        $this->assertSame(4, $this->available());
        $this->postOrder('first-buyer', quantity: 2)->assertCreated();

        $this->assertNull(DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
        $this->assertSame(2, $this->available());
    }

    public function test_a_mixed_order_takes_the_last_unsold_ticket_and_the_listings(): void
    {
        DB::table('product_prices')->where('id', $this->priceId)->update(['initial_quantity_available' => 3]);
        $this->list();
        $this->list(attendeeOffset: 1);

        $orderId = $this->orderId($this->postOrder('first-buyer', quantity: 3)->assertCreated()->json('data.short_id'));

        $this->assertSame(2, DB::table('ticket_resale_listings')->where('claimed_by_order_id', $orderId)->count());
        $this->assertSame(0, $this->available());
    }

    public function test_an_order_for_more_than_the_unsold_and_listed_tickets_is_refused_and_holds_nothing(): void
    {
        $listingId = $this->list();

        $this->postOrder('first-buyer', quantity: 2)->assertUnprocessable();

        $this->assertNull(DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
        $this->assertSame(1, $this->available());
    }

    public function test_a_listing_adds_no_stock_once_withdrawn_past_the_cutoff_or_when_resale_or_the_event_is_off(): void
    {
        $listingId = $this->list();

        DB::table('ticket_resale_listings')->where('id', $listingId)->update(['withdrawal_requested_at' => now()]);
        $this->assertSame(0, $this->available());
        DB::table('ticket_resale_listings')->where('id', $listingId)->update(['withdrawal_requested_at' => null]);

        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_ticket_resale' => false]);
        $this->assertSame(0, $this->available());
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_ticket_resale' => true]);

        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::DRAFT->name]);
        $this->assertSame(0, $this->available());
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);

        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => now()->addMinutes(119)]);
        $this->assertSame(0, $this->available());
        $this->postOrder('late-buyer')->assertUnprocessable();
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => now()->addDays(30)]);

        DB::table('ticket_resale_listings')->where('id', $listingId)->update(['status' => TicketResaleListingStatus::CLOSED->name]);
        $this->assertSame(0, $this->available());
    }

    public function test_a_listing_paid_on_a_stripe_account_the_organizer_no_longer_uses_adds_no_stock(): void
    {
        $listingId = $this->list();
        DB::table('ticket_resale_listings')->where('id', $listingId)->update(['connected_account_id' => 'acct_previous']);

        $this->assertSame(0, $this->available());
        $this->postOrder('first-buyer')->assertUnprocessable();

        DB::table('organizer_stripe_platforms')->insert([
            'organizer_id' => $this->organizerId,
            'stripe_account_id' => 'acct_previous',
            'stripe_setup_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, $this->available());
    }

    public function test_a_price_lowered_below_what_the_seller_was_quoted_stops_the_listing_selling(): void
    {
        $this->list();

        DB::table('product_prices')->where('id', $this->priceId)->update(['price' => 8.00]);

        $this->assertSame(0, $this->available());
        $this->postOrder('first-buyer')->assertUnprocessable();
    }

    public function test_a_discounted_order_cannot_take_a_listing(): void
    {
        $listingId = $this->list();
        DB::table('promo_codes')->insert([
            'code' => 'half',
            'event_id' => $this->eventId,
            'discount' => 50,
            'discount_type' => PromoCodeDiscountTypeEnum::PERCENTAGE->name,
            'discount_applies_to' => PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name,
            'applicable_product_ids' => json_encode([$this->productId]),
            'order_usage_count' => 0,
            'attendee_usage_count' => 0,
            'created_at' => now(),
        ]);

        $this->postOrder('first-buyer', promoCode: 'half')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['products' => 'can only be bought at full price']);

        $this->assertNull(DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
        $this->assertSame(0, DB::table('orders')->where('event_id', $this->eventId)->where('status', OrderStatus::RESERVED->name)->whereNull('deleted_at')->count());
    }

    public function test_a_listing_on_one_ticket_type_leaves_another_type_sharing_its_full_pool_sold_out(): void
    {
        $otherProductId = $this->insertProduct();
        $otherPriceId = $this->insertPrice($otherProductId, null, ProductQuantityAppliesTo::EVENT->name);
        DB::table('product_prices')->where('id', $this->priceId)->update(['initial_quantity_available' => null]);

        $poolId = DB::table('capacity_assignments')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Shared pool',
            'capacity' => 2,
            'used_capacity' => 2,
            'applies_to' => 'PRODUCTS',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_capacity_assignments')->insert([
            ['product_id' => $this->productId, 'capacity_assignment_id' => $poolId, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $otherProductId, 'capacity_assignment_id' => $poolId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $listingId = $this->list();

        $this->assertSame(1, $this->available());
        $this->assertSame(0, $this->available(priceId: $otherPriceId));
        $this->postOrder('other-type-buyer', productId: $otherProductId, priceId: $otherPriceId)->assertUnprocessable();

        $orderId = $this->orderId($this->postOrder('listed-type-buyer')->assertCreated()->json('data.short_id'));
        $this->assertSame($orderId, DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
    }

    public function test_a_listing_is_purchasable_on_a_date_whose_own_capacity_is_full(): void
    {
        DB::table('product_prices')->where('id', $this->priceId)->update(['initial_quantity_available' => null]);
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['capacity' => 2, 'used_capacity' => 2]);
        $listingId = $this->list();

        $this->assertSame(1, $this->available());
        $this->assertSame(EventOccurrenceStatus::ACTIVE->name, $this->publicDateStatus());

        $orderId = $this->orderId($this->postOrder('first-buyer')->assertCreated()->json('data.short_id'));

        $this->assertSame($orderId, DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
        $this->assertSame(EventOccurrenceStatus::SOLD_OUT->name, $this->publicDateStatus());
        $this->postOrder('second-buyer')->assertUnprocessable();
    }

    private function publicDateStatus(): string
    {
        return $this->app->make(ResaleOccurrenceAvailabilityService::class)
            ->markOccurrencesWithListedTickets(
                $this->eventId,
                collect([$this->app->make(EventOccurrenceRepositoryInterface::class)->findById($this->occurrenceId)]),
            )
            ->first()
            ->getStatus();
    }

    public function test_a_listing_adds_stock_to_its_own_date_only(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['type' => EventType::RECURRING->name]);
        DB::table('product_prices')->where('id', $this->priceId)->update([
            'quantity_applies_to' => ProductQuantityAppliesTo::OCCURRENCE->name,
        ]);
        $otherOccurrenceId = $this->insertOccurrence(daysAhead: 40);
        $this->sellTickets($this->productId, $this->priceId, $otherOccurrenceId, 2);

        $this->list();

        $this->assertSame(1, $this->available());
        $this->assertSame(0, $this->available(occurrenceId: $otherOccurrenceId));
        $this->postOrder('other-date-buyer', occurrenceId: $otherOccurrenceId)->assertUnprocessable();
        $this->postOrder('listed-date-buyer')->assertCreated();
    }

    public function test_a_listing_on_a_sold_out_tier_does_not_take_the_next_tier_off_sale(): void
    {
        DB::table('products')->where('id', $this->productId)->update([
            'type' => 'TIERED',
            'sequential_tier_release_enabled' => true,
        ]);
        DB::table('product_prices')->where('id', $this->priceId)->update(['label' => 'Early', 'order' => 1]);
        $secondTierId = $this->insertPrice($this->productId, 5, ProductQuantityAppliesTo::EVENT->name, label: 'Standard', price: 15.00);
        DB::table('product_prices')->where('id', $secondTierId)->update(['order' => 2]);

        $listingId = $this->list();

        $this->postOrder('second-tier-buyer', priceId: $secondTierId)->assertCreated();
        $orderId = $this->orderId($this->postOrder('first-tier-buyer')->assertCreated()->json('data.short_id'));
        $this->assertSame($orderId, DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
    }

    public function test_a_checkout_holding_a_listing_cannot_run_past_the_resale_cutoff(): void
    {
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => now()->addMinutes(125)]);
        $this->list();

        $orderId = $this->orderId($this->postOrder('first-buyer')->assertCreated()->json('data.short_id'));

        $reservedUntil = Carbon::parse(DB::table('orders')->where('id', $orderId)->value('reserved_until'));
        $this->assertTrue($reservedUntil->lte(now()->addMinutes(5)->addSecond()));
        $this->assertTrue($reservedUntil->isFuture());
    }

    public function test_a_reservation_backed_by_a_listing_does_not_use_up_unsold_stock_that_frees_up_later(): void
    {
        $this->list();
        $this->postOrder('first-buyer')->assertCreated();

        DB::table('product_prices')->where('id', $this->priceId)->update(['quantity_sold' => 1]);

        $this->assertSame(1, $this->available(includeResaleStock: false));
    }

    public function test_a_listed_ticket_is_offered_to_the_next_person_waiting_and_their_offer_holds_it(): void
    {
        Queue::fake([SendWaitlistOfferEmailJob::class]);

        DB::table('event_settings')->where('event_id', $this->eventId)->update([
            'waitlist_enabled' => true,
            'waitlist_auto_process' => true,
            'waitlist_offer_timeout_minutes' => 720,
        ]);
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['start_date' => now()->addHours(5)]);
        $entryId = $this->insertWaitlistEntry('first@example.test');
        $laterEntryId = $this->insertWaitlistEntry('second@example.test');
        $listingId = $this->list();

        event(new CapacityChangedEvent(
            eventId: $this->eventId,
            direction: CapacityChangeDirection::INCREASED,
            productId: $this->productId,
            productPriceId: $this->priceId,
            eventOccurrenceId: $this->occurrenceId,
        ));

        $entry = DB::table('waitlist_entries')->where('id', $entryId)->first();
        $this->assertSame(WaitlistEntryStatus::OFFERED->name, $entry->status);
        $this->assertSame($entry->order_id, DB::table('ticket_resale_listings')->where('id', $listingId)->value('claimed_by_order_id'));
        $this->assertSame(WaitlistEntryStatus::WAITING->name, DB::table('waitlist_entries')->where('id', $laterEntryId)->value('status'));

        $cutoff = now()->addHours(3);
        $this->assertTrue(Carbon::parse($entry->offer_expires_at)->lte($cutoff->copy()->addSecond()));
        $this->assertTrue(Carbon::parse(DB::table('orders')->where('id', $entry->order_id)->value('reserved_until'))->lte($cutoff->copy()->addSecond()));
        $this->assertSame(0, $this->available());
        Queue::assertPushed(SendWaitlistOfferEmailJob::class, 1);
    }

    private function insertWaitlistEntry(string $email): int
    {
        return DB::table('waitlist_entries')->insertGetId([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'product_price_id' => $this->priceId,
            'email' => $email,
            'first_name' => 'Wai',
            'last_name' => 'Ting',
            'status' => WaitlistEntryStatus::WAITING->name,
            'locale' => 'en',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }

    private function orderId(string $shortId): int
    {
        return DB::table('orders')->where('short_id', $shortId)->value('id');
    }

    private function list(int $attendeeOffset = 0, float $minUnitPrice = 10.00): int
    {
        $attendeeId = DB::table('attendees')
            ->where('order_id', $this->sellerOrderId)
            ->orderBy('id')
            ->skip($attendeeOffset)
            ->value('id');

        return DB::table('ticket_resale_listings')->insertGetId([
            'event_id' => $this->eventId,
            'order_id' => $this->sellerOrderId,
            'attendee_id' => $attendeeId,
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'status' => TicketResaleListingStatus::LISTED->name,
            'min_unit_price' => $minUnitPrice,
            'estimated_refund' => 9.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function available(bool $includeResaleStock = true, ?int $priceId = null, ?int $occurrenceId = null): int
    {
        return $this->app->make(AvailableProductQuantitiesFetchService::class)
            ->getAvailableProductQuantities(
                $this->eventId,
                ignoreCache: true,
                eventOccurrenceId: $occurrenceId ?? $this->occurrenceId,
                includeResaleStock: $includeResaleStock,
            )
            ->getAvailableQuantityForPrice($priceId ?? $this->priceId);
    }

    private function eventWideAvailable(): int
    {
        return $this->app->make(AvailableProductQuantitiesFetchService::class)
            ->getAvailableProductQuantities($this->eventId, ignoreCache: true, includeResaleStock: true)
            ->getAvailableQuantityForPrice($this->priceId);
    }

    private function postOrder(
        string $sessionIdentifier,
        int $quantity = 1,
        ?int $productId = null,
        ?int $priceId = null,
        ?int $occurrenceId = null,
        ?string $promoCode = null,
    ): TestResponse {
        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            $route->flushController();
        }

        return $this->postJson("/public/events/{$this->eventId}/order?session_identifier={$sessionIdentifier}", array_filter([
            'promo_code' => $promoCode,
            'products' => [[
                'product_id' => $productId ?? $this->productId,
                'event_occurrence_id' => $occurrenceId ?? $this->occurrenceId,
                'quantities' => [['price_id' => $priceId ?? $this->priceId, 'quantity' => $quantity]],
            ]],
        ], static fn ($value) => $value !== null));
    }
}
