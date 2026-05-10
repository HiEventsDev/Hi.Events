<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\OrderItemRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integration test for OrderItemRepository::getReservedQuantityForOccurrence().
 *
 * Exercises the real production schema. Uses User::factory()->withAccount() to bootstrap
 * an account + user, then raw DB inserts for the rest of the FK chain so the test does not
 * depend on factories that the codebase does not yet provide for organizers / events /
 * products / orders. The DatabaseTransactions trait rolls everything back per test.
 */
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

        // Bootstrap account + user via factory.
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
            'short_id' => 'test_evt_' . uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->occurrenceId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_' . uniqid(),
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
            'short_id' => 'occ_' . uniqid(),
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

    public function testReturnsZeroWhenNoReservations(): void
    {
        $this->assertSame(0, $this->repository->getReservedQuantityForOccurrence($this->occurrenceId));
    }

    public function testSumsActiveReservationsForOccurrence(): void
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

        $this->assertSame(5, $this->repository->getReservedQuantityForOccurrence($this->occurrenceId));
    }

    public function testIgnoresExpiredReservations(): void
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

        $this->assertSame(4, $this->repository->getReservedQuantityForOccurrence($this->occurrenceId));
    }

    public function testIgnoresNonReservedOrders(): void
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

        $this->assertSame(1, $this->repository->getReservedQuantityForOccurrence($this->occurrenceId));
    }

    public function testIgnoresSoftDeletedOrders(): void
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

        $this->assertSame(2, $this->repository->getReservedQuantityForOccurrence($this->occurrenceId));
    }

    public function testScopesByOccurrenceId(): void
    {
        $this->insertOrderWithItems(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addHour(),
            occurrenceQuantities: [
                $this->occurrenceId => 3,
                $this->otherOccurrenceId => 7,
            ],
        );

        $this->assertSame(3, $this->repository->getReservedQuantityForOccurrence($this->occurrenceId));
        $this->assertSame(7, $this->repository->getReservedQuantityForOccurrence($this->otherOccurrenceId));
    }

    public function testIgnoresSoftDeletedOrderItems(): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_' . uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => OrderStatus::RESERVED->name,
            'reserved_until' => now()->addHour(),
            'public_id' => 'pub_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // One live item (counts), one soft-deleted item (does not).
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

        $this->assertSame(4, $this->repository->getReservedQuantityForOccurrence($this->occurrenceId));
    }

    /**
     * @param array<int, int> $occurrenceQuantities Map of event_occurrence_id => quantity
     */
    private function insertOrderWithItems(
        string $status,
        \DateTimeInterface $reservedUntil,
        array $occurrenceQuantities,
        ?\DateTimeInterface $deletedAt = null,
    ): int {
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_' . uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => $status,
            'reserved_until' => $reservedUntil,
            'public_id' => 'pub_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deletedAt,
        ]);

        foreach ($occurrenceQuantities as $occurrenceId => $quantity) {
            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $this->productId,
                'product_price_id' => $this->productPriceId,
                'event_occurrence_id' => $occurrenceId,
                'quantity' => $quantity,
                'price' => 10.00,
                'total_before_additions' => $quantity * 10.00,
            ]);
        }

        return $orderId;
    }
}
