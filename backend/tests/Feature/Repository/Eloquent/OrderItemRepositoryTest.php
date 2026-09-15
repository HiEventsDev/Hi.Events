<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\OrderItemRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderItemRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OrderItemRepository $repository;

    private int $eventId;

    private int $occurrenceId;

    private int $otherOccurrenceId;

    private int $productId;

    private int $productPriceId;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(OrderItemRepository::class);

        $user = User::factory()->withAccount()->create();
        $this->accountId = $user->accounts()->first()->id;

        $now = now()->toDateTimeString();

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Test Event',
            'account_id' => $this->accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'test_evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->occurrenceId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $this->eventId,
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date' => now()->addDays(1)->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->otherOccurrenceId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $this->eventId,
            'start_date' => now()->addDays(2)->toDateTimeString(),
            'end_date' => now()->addDays(2)->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'title' => 'Test Product',
            'event_id' => $this->eventId,
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productPriceId = DB::table('product_prices')->insertGetId([
            'product_id' => $this->productId,
            'price' => 10.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_returns_zero_when_no_reservations(): void
    {
        $this->assertSame(0, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
    }

    public function test_sums_active_reservations_for_occurrence(): void
    {
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 3],
        );
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addMinutes(30),
            occurrenceQuantities: [$this->occurrenceId => 2],
        );

        $this->assertSame(5, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
    }

    public function test_ignores_expired_reservations(): void
    {
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->subMinute(),
            occurrenceQuantities: [$this->occurrenceId => 5],
        );
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 4],
        );

        $this->assertSame(4, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
    }

    public function test_ignores_non_reserved_orders(): void
    {
        $this->insertOrderWithItems(
            status: OrderStatus::COMPLETED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 7],
        );
        $this->insertOrderWithItems(
            status: OrderStatus::CANCELLED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 9],
        );
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 1],
        );

        $this->assertSame(1, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
    }

    public function test_ignores_soft_deleted_orders(): void
    {
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 6],
            deletedAt: now(),
        );
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 2],
        );

        $this->assertSame(2, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
    }

    public function test_scopes_by_occurrence_id(): void
    {
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [
                $this->occurrenceId => 3,
                $this->otherOccurrenceId => 7,
            ],
        );

        $this->assertSame(3, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
        $this->assertSame(7, $this->repository->getReservedTicketQuantityForOccurrence($this->otherOccurrenceId));
    }

    public function test_ignores_general_product_order_items(): void
    {
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 9],
            productType: ProductType::GENERAL->name,
        );
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [$this->occurrenceId => 2],
        );

        $this->assertSame(2, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
    }

    public function test_ignores_soft_deleted_order_items(): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => OrderStatus::RESERVED->name,
            'reserved_until' => now()->addHour(),
            'public_id' => 'pub_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'event_occurrence_id' => $this->occurrenceId,
            'quantity' => 4,
            'price' => 10.00,
            'total_before_additions' => 40.00,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'event_occurrence_id' => $this->occurrenceId,
            'quantity' => 6,
            'price' => 10.00,
            'total_before_additions' => 60.00,
            'deleted_at' => now(),
        ]);

        $this->assertSame(4, $this->repository->getReservedTicketQuantityForOccurrence($this->occurrenceId));
    }

    public function test_reserved_quantities_by_price_include_general_products(): void
    {
        $otherPriceId = DB::table('product_prices')->insertGetId([
            'product_id' => $this->productId,
            'price' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertOrderWithItems(OrderStatus::RESERVED->name, now()->addHour(), [$this->occurrenceId => 3]);
        $this->insertOrderWithItems(OrderStatus::RESERVED->name, now()->addHour(), [$this->occurrenceId => 2], productType: ProductType::GENERAL->name, productPriceId: $otherPriceId);
        $this->insertOrderWithItems(OrderStatus::RESERVED->name, now()->subMinute(), [$this->occurrenceId => 9]);
        $this->insertOrderWithItems(OrderStatus::RESERVED->name, now()->addHour(), [$this->otherOccurrenceId => 4]);

        $this->assertSame(
            [$this->productPriceId => 3, $otherPriceId => 2],
            $this->repository->getReservedQuantitiesByPrice($this->eventId, $this->occurrenceId),
        );
    }

    public function test_reserved_quantities_by_price_for_the_whole_event_span_occurrences_and_skip_deleted_items(): void
    {
        $this->insertOrderWithItems(OrderStatus::RESERVED->name, now()->addHour(), [$this->occurrenceId => 3, $this->otherOccurrenceId => 4]);
        $this->insertOrderWithItems(OrderStatus::RESERVED->name, now()->subMinute(), [$this->occurrenceId => 9]);
        $this->insertOrderWithItems(OrderStatus::COMPLETED->name, now(), [$this->occurrenceId => 5]);
        $deletedItemsOrder = $this->insertOrderWithItems(OrderStatus::RESERVED->name, now()->addHour(), [$this->occurrenceId => 6]);
        DB::table('order_items')->where('order_id', $deletedItemsOrder)->update(['deleted_at' => now()]);

        $this->assertSame([$this->productPriceId => 7], $this->repository->getReservedQuantitiesByPrice($this->eventId));
    }

    public function test_sold_general_quantities_by_price_count_completed_and_offline_pending_orders(): void
    {
        $this->insertOrderWithItems(OrderStatus::COMPLETED->name, now(), [$this->occurrenceId => 2], productType: ProductType::GENERAL->name);
        $this->insertOrderWithItems(OrderStatus::AWAITING_OFFLINE_PAYMENT->name, now(), [$this->occurrenceId => 1], productType: ProductType::GENERAL->name);
        $this->insertOrderWithItems(OrderStatus::CANCELLED->name, now(), [$this->occurrenceId => 5], productType: ProductType::GENERAL->name);
        $this->insertOrderWithItems(OrderStatus::COMPLETED->name, now(), [$this->occurrenceId => 7]);
        $this->insertOrderWithItems(OrderStatus::COMPLETED->name, now(), [$this->otherOccurrenceId => 4], productType: ProductType::GENERAL->name);

        $this->assertSame([$this->productPriceId => 3], $this->repository->getSoldQuantitiesByPriceForOccurrence($this->occurrenceId));
    }

    public function test_max_sold_general_quantity_on_a_single_occurrence(): void
    {
        $this->insertOrderWithItems(OrderStatus::COMPLETED->name, now(), [$this->occurrenceId => 2, $this->otherOccurrenceId => 5], productType: ProductType::GENERAL->name);
        $this->insertOrderWithItems(OrderStatus::COMPLETED->name, now(), [$this->occurrenceId => 1], productType: ProductType::GENERAL->name);

        $this->assertSame([$this->productPriceId => 5], $this->repository->getMaxSoldPerOccurrenceByPrice([$this->productPriceId]));
    }

    /**
     * @param  array<int, int>  $occurrenceQuantities  Map of event_occurrence_id => quantity
     */
    private function insertOrderWithItems(
        string $status,
        \DateTimeInterface $reservedUntil,
        array $occurrenceQuantities,
        ?\DateTimeInterface $deletedAt = null,
        string $productType = ProductType::TICKET->name,
        ?int $productPriceId = null,
    ): int {
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => $status,
            'reserved_until' => $reservedUntil,
            'public_id' => 'pub_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deletedAt,
        ]);

        foreach ($occurrenceQuantities as $occurrenceId => $quantity) {
            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $this->productId,
                'product_price_id' => $productPriceId ?? $this->productPriceId,
                'product_type' => $productType,
                'event_occurrence_id' => $occurrenceId,
                'quantity' => $quantity,
                'price' => 10.00,
                'total_before_additions' => $quantity * 10.00,
            ]);
        }

        return $orderId;
    }
}
