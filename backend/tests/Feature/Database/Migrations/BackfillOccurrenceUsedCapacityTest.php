<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Migrations;

use HiEvents\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillOccurrenceUsedCapacityTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION_PATH = __DIR__.'/../../../../database/migrations/2026_07_10_000001_backfill_occurrence_used_capacity.php';

    private int $accountId;

    private int $userId;

    private int $organizerId;

    private int $eventId;

    private int $productId;

    private int $productPriceId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;
        $this->organizerId = $this->insertOrganizer();
        $this->eventId = $this->insertEvent();
        $this->productId = $this->insertProduct();
        $this->productPriceId = $this->insertProductPrice($this->productId);
    }

    public function test_counts_completed_and_offline_pending_spots_only(): void
    {
        $occurrenceId = $this->insertOccurrence(usedCapacity: 0);

        $completed = $this->insertOrder('COMPLETED');
        $this->insertAttendee($completed, $occurrenceId, 'ACTIVE');
        $this->insertAttendee($completed, $occurrenceId, 'ACTIVE');

        $offline = $this->insertOrder('AWAITING_OFFLINE_PAYMENT');
        $this->insertAttendee($offline, $occurrenceId, 'AWAITING_PAYMENT');

        $reserved = $this->insertOrder('RESERVED');
        $this->insertAttendee($reserved, $occurrenceId, 'AWAITING_PAYMENT');

        $cancelled = $this->insertOrder('CANCELLED');
        $this->insertAttendee($cancelled, $occurrenceId, 'CANCELLED');

        $this->migration()->up();

        $this->assertSame(3, (int) DB::table('event_occurrences')->where('id', $occurrenceId)->value('used_capacity'));
    }

    public function test_resets_inflated_counter_when_no_qualifying_attendees(): void
    {
        $occurrenceId = $this->insertOccurrence(usedCapacity: 99);

        $reserved = $this->insertOrder('RESERVED');
        $this->insertAttendee($reserved, $occurrenceId, 'AWAITING_PAYMENT');

        $this->migration()->up();

        $this->assertSame(0, (int) DB::table('event_occurrences')->where('id', $occurrenceId)->value('used_capacity'));
    }

    public function test_ignores_soft_deleted_attendees(): void
    {
        $occurrenceId = $this->insertOccurrence(usedCapacity: 0);

        $completed = $this->insertOrder('COMPLETED');
        $this->insertAttendee($completed, $occurrenceId, 'ACTIVE');
        $this->insertAttendee($completed, $occurrenceId, 'ACTIVE', deleted: true);

        $this->migration()->up();

        $this->assertSame(1, (int) DB::table('event_occurrences')->where('id', $occurrenceId)->value('used_capacity'));
    }

    private function migration(): Migration
    {
        return require self::MIGRATION_PATH;
    }

    private function insertOrganizer(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Capacity Organizer',
            'email' => 'org+'.uniqid().'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEvent(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('events')->insertGetId([
            'title' => 'Capacity Event '.uniqid(),
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertProduct(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('products')->insertGetId([
            'title' => 'Capacity Ticket',
            'event_id' => $this->eventId,
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertProductPrice(int $productId): int
    {
        $now = now()->toDateTimeString();

        return DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 1000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertOccurrence(int $usedCapacity): int
    {
        $now = now()->toDateTimeString();

        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $this->eventId,
            'start_date' => $now,
            'used_capacity' => $usedCapacity,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertOrder(string $status): int
    {
        $now = now()->toDateTimeString();

        return DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => $status,
            'public_id' => 'PUB_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertAttendee(int $orderId, int $occurrenceId, string $status, bool $deleted = false): void
    {
        $now = now()->toDateTimeString();

        DB::table('attendees')->insert([
            'short_id' => 'att_'.uniqid(),
            'email' => 'attendee+'.uniqid().'@example.test',
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'event_id' => $this->eventId,
            'event_occurrence_id' => $occurrenceId,
            'public_id' => 'ATT_'.uniqid(),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deleted ? $now : null,
        ]);
    }
}
