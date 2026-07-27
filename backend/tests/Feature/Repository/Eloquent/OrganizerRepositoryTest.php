<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use Carbon\Carbon;
use HiEvents\Models\User;
use HiEvents\Repository\DTO\Organizer\OrganizerDailyStatsResponseDTO;
use HiEvents\Repository\Eloquent\OrganizerRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizerRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OrganizerRepository $repository;

    private int $accountId;

    private int $organizerId;

    private int $eventAId;

    private int $eventBId;

    private int $occurrenceAId;

    private int $occurrenceBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(OrganizerRepository::class);

        $user = User::factory()->withAccount()->create();
        $this->accountId = $user->accounts()->first()->id;

        $now = now()->toDateTimeString();

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Stats Organizer',
            'email' => 'stats-organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventAId = DB::table('events')->insertGetId([
            'title' => 'Event A',
            'account_id' => $this->accountId,
            'user_id' => $user->id,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_a_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventBId = DB::table('events')->insertGetId([
            'title' => 'Event B',
            'account_id' => $this->accountId,
            'user_id' => $user->id,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_b_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->occurrenceAId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_a_'.uniqid(),
            'event_id' => $this->eventAId,
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date' => now()->addDay()->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->occurrenceBId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_b_'.uniqid(),
            'event_id' => $this->eventBId,
            'start_date' => now()->addDays(2)->toDateTimeString(),
            'end_date' => now()->addDays(2)->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_get_organizer_stats_returns_daily_breakdown_and_aggregates_across_date_window(): void
    {
        $today = Carbon::now()->startOfDay();
        $rangeStart = (clone $today)->subDays(59);

        $rows = [];
        for ($i = 0; $i < 60; $i++) {
            $date = (clone $rangeStart)->addDays($i)->toDateString();

            $rows[] = $this->dailyRow(
                eventId: $this->eventAId,
                occurrenceId: $this->occurrenceAId,
                date: $date,
                productsSold: 2,
                attendeesRegistered: 3,
                salesGross: 10.00,
                ordersCreated: 1,
                totalRefunded: 1.50,
            );
            $rows[] = $this->dailyRow(
                eventId: $this->eventBId,
                occurrenceId: $this->occurrenceBId,
                date: $date,
                productsSold: 5,
                attendeesRegistered: 7,
                salesGross: 25.00,
                ordersCreated: 2,
                totalRefunded: 0.50,
            );
        }
        DB::table('event_occurrence_daily_statistics')->insert($rows);

        $endDate = (clone $today)->format('Y-m-d H:i:s');
        $startDate = (clone $today)->subDays(29)->format('Y-m-d H:i:s');

        $stats = $this->repository->getOrganizerStats(
            organizerId: $this->organizerId,
            accountId: $this->accountId,
            currencyCode: 'USD',
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertCount(30, $stats->daily_stats);
        $stats->daily_stats->each(function ($row) {
            $this->assertInstanceOf(OrganizerDailyStatsResponseDTO::class, $row);
        });

        $firstDay = $stats->daily_stats->first();
        $this->assertSame(7, $firstDay->products_sold);
        $this->assertSame(10, $firstDay->attendees_registered);
        $this->assertSame(35.0, $firstDay->total_sales_gross);
        $this->assertSame(3, $firstDay->orders_created);
        $this->assertSame(2.0, $firstDay->total_refunded);

        $this->assertSame(7 * 30, $stats->total_products_sold);
        $this->assertSame(10 * 30, $stats->total_attendees_registered);
        $this->assertSame(3 * 30, $stats->total_orders);
        $this->assertEqualsWithDelta(35.0 * 30, $stats->total_gross_sales, 0.001);
        $this->assertEqualsWithDelta(2.0 * 30, $stats->total_refunded, 0.001);

        $this->assertSame('USD', $stats->currency_code);
        $this->assertSame($startDate, $stats->start_date);
        $this->assertSame($endDate, $stats->end_date);

        $this->assertContains('USD', $stats->all_organizers_currencies);
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyRow(
        int $eventId,
        int $occurrenceId,
        string $date,
        int $productsSold,
        int $attendeesRegistered,
        float $salesGross,
        int $ordersCreated,
        float $totalRefunded,
    ): array {
        $now = now()->toDateTimeString();

        return [
            'event_id' => $eventId,
            'event_occurrence_id' => $occurrenceId,
            'date' => $date,
            'products_sold' => $productsSold,
            'attendees_registered' => $attendeesRegistered,
            'sales_total_gross' => $salesGross,
            'sales_total_before_additions' => $salesGross,
            'total_tax' => 0,
            'total_fee' => 0,
            'orders_created' => $ordersCreated,
            'orders_cancelled' => 0,
            'total_refunded' => $totalRefunded,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
