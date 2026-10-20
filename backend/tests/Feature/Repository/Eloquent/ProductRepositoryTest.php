<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use DateTimeInterface;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\ProductRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ProductRepository $repository;

    private int $eventId;

    private int $productId;

    private int $productPriceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(ProductRepository::class);

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $now = now()->toDateTimeString();

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Test Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'test_evt_'.uniqid(),
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

    public function test_returns_false_when_product_has_no_orders(): void
    {
        $this->assertFalse($this->repository->hasAssociatedOrders($this->productId));
    }

    public function test_unexpired_reserved_order_blocks_deletion(): void
    {
        $this->insertOrderWithItem(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addMinutes(15),
        );

        $this->assertTrue($this->repository->hasAssociatedOrders($this->productId));
    }

    public function test_expired_reserved_order_does_not_block_deletion(): void
    {
        $this->insertOrderWithItem(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->subMinute(),
        );

        $this->assertFalse($this->repository->hasAssociatedOrders($this->productId));
    }

    public function test_soft_deleted_reserved_order_does_not_block_deletion(): void
    {
        $this->insertOrderWithItem(
            status: OrderStatus::RESERVED->name,
            reservedUntil: now()->addMinutes(15),
            deletedAt: now(),
        );

        $this->assertFalse($this->repository->hasAssociatedOrders($this->productId));
    }

    public function test_soft_deleted_completed_order_blocks_deletion(): void
    {
        $this->insertOrderWithItem(
            status: OrderStatus::COMPLETED->name,
            reservedUntil: now()->subHour(),
            deletedAt: now(),
        );

        $this->assertTrue($this->repository->hasAssociatedOrders($this->productId));
    }

    public function test_completed_order_blocks_deletion(): void
    {
        $this->insertOrderWithItem(
            status: OrderStatus::COMPLETED->name,
            reservedUntil: now()->subHour(),
        );

        $this->assertTrue($this->repository->hasAssociatedOrders($this->productId));
    }

    public function test_abandoned_order_does_not_block_deletion(): void
    {
        $this->insertOrderWithItem(
            status: OrderStatus::ABANDONED->name,
            reservedUntil: now()->addHour(),
        );

        $this->assertFalse($this->repository->hasAssociatedOrders($this->productId));
    }

    private function insertOrderWithItem(
        string $status,
        DateTimeInterface $reservedUntil,
        ?DateTimeInterface $deletedAt = null,
    ): void {
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => $status,
            'reserved_until' => $reservedUntil,
            'deleted_at' => $deletedAt,
            'public_id' => 'pub_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'quantity' => 1,
            'price' => 10.00,
            'total_before_additions' => 10.00,
        ]);
    }
}
