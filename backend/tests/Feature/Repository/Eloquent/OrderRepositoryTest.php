<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\OrderRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OrderRepository $repository;

    private int $accountId;

    private int $userId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(OrderRepository::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_organizer_orders_exclude_orders_from_deleted_events(): void
    {
        $liveEventId = $this->createEvent();
        $deletedEventId = $this->createEvent();

        $liveOrderId = $this->createOrder($liveEventId);
        $this->createOrder($deletedEventId);

        DB::table('events')->where('id', $deletedEventId)->update(['deleted_at' => now()]);

        $orders = $this->repository->findByOrganizerId($this->organizerId, $this->accountId, new QueryParamsDTO);

        $this->assertSame([$liveOrderId], collect($orders->items())->map(fn ($order) => $order->getId())->all());
    }

    private function createEvent(): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Test Event',
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'test_evt_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $eventId): int
    {
        return DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $eventId,
            'currency' => 'USD',
            'status' => OrderStatus::COMPLETED->name,
            'public_id' => 'pub_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
