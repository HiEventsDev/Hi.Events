<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Application\Handlers\Admin;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\AdminDashboardPeriod;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Admin\DTO\AdminDashboardResponseDTO;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAdminDashboardDataDTO;
use HiEvents\Services\Application\Handlers\Admin\GetAdminDashboardDataHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GetAdminDashboardDataHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private const TEST_CURRENCY = 'ZZZ';

    private int $accountId;

    private int $organizerId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Dashboard Organizer',
            'email' => 'dashboard-organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_most_viewed_counts_only_views_inside_the_period(): void
    {
        $staleEventId = $this->createEvent('Stale popular event');
        $freshEventId = $this->createEvent('Fresh event');

        $this->createDailyStatistic($staleEventId, now()->subDays(60), views: 50000);
        $this->createDailyStatistic($staleEventId, now(), views: 1);
        $this->createDailyStatistic($freshEventId, now()->subDays(3), views: 300);
        $this->createDailyStatistic($freshEventId, now(), views: 20);

        $views = $this->viewsByEventId($this->handle(AdminDashboardPeriod::LAST_7_DAYS));

        $this->assertSame(320, $views[$freshEventId]);
        $this->assertSame(1, $views[$staleEventId]);
    }

    public function test_most_viewed_uses_whole_utc_days_for_views_and_orders(): void
    {
        $eventId = $this->createEvent('Whole day event');
        $windowStart = now('UTC')->subDay()->startOfDay();
        $this->createDailyStatistic($eventId, $windowStart, views: 40);
        $this->createOrder($eventId, $windowStart->copy()->addMinute(), 10.00);

        $result = $this->handle(AdminDashboardPeriod::LAST_24_HOURS);
        $row = $this->findEventRow($result->most_viewed_events, $eventId);

        $this->assertSame($windowStart->toDateString(), $result->views_since);
        $this->assertSame(40, $row['views']);
        $this->assertSame(1, $row['orders_count']);
    }

    public function test_most_viewed_excludes_events_with_no_views_in_period(): void
    {
        $eventId = $this->createEvent('Old event');
        $this->createDailyStatistic($eventId, now()->subDays(20), views: 9000);

        $views = $this->viewsByEventId($this->handle(AdminDashboardPeriod::LAST_7_DAYS));

        $this->assertArrayNotHasKey($eventId, $views);
    }

    public function test_top_selling_events_only_count_orders_inside_the_period(): void
    {
        $eventId = $this->createEvent('Selling event');
        $this->createOrder($eventId, now()->subHours(2), 10.00);
        $this->createOrder($eventId, now()->subDays(3), 20.00);
        $this->createOrder($eventId, now()->subDays(3), 99.00, status: 'CANCELLED');

        $dayRow = $this->findEventRow($this->handle(AdminDashboardPeriod::LAST_24_HOURS)->top_selling_events, $eventId);
        $weekRow = $this->findEventRow($this->handle(AdminDashboardPeriod::LAST_7_DAYS)->top_selling_events, $eventId);

        $this->assertSame(1, $dayRow['orders_count']);
        $this->assertSame(10.0, $dayRow['sales_total']);
        $this->assertSame(2, $weekRow['orders_count']);
        $this->assertSame(30.0, $weekRow['sales_total']);
    }

    public function test_sales_are_split_by_currency(): void
    {
        $eventId = $this->createEvent('Currency event');
        $this->createOrder($eventId, now()->subHour(), 15.00);
        $this->createOrder($eventId, now()->subHour(), 5.00, totalRefunded: 4.00);

        $sales = collect($this->handle(AdminDashboardPeriod::LAST_24_HOURS)->sales_by_currency)
            ->firstWhere('currency', self::TEST_CURRENCY);

        $this->assertSame(2, $sales['orders_count']);
        $this->assertSame(16.0, $sales['total']);
    }

    public function test_commission_is_grouped_by_currency_for_each_period(): void
    {
        $eventId = $this->createEvent('Commission event');
        $this->createPlatformFee($this->createOrder($eventId, now()->subHours(3), 100.00), now()->subHours(3), gross: 1.23, net: 1.00);
        $this->createPlatformFee($this->createOrder($eventId, now()->subDays(5), 100.00), now()->subDays(5), gross: 2.46, net: null);
        $this->createPlatformFee($this->createOrder($eventId, now()->subDays(20), 100.00), now()->subDays(20), gross: 5.00, net: 4.00);
        $this->createPlatformFee($this->createOrder($eventId, now()->subDays(45), 100.00), now()->subDays(45), gross: 50.00, net: 40.00);

        $commission = collect($this->handle(AdminDashboardPeriod::LAST_7_DAYS)->commission_by_currency)
            ->firstWhere('currency', self::TEST_CURRENCY);

        $this->assertEquals(['gross' => 1.23, 'net' => 1.00], $commission['24h']);
        $this->assertEquals(['gross' => 3.69, 'net' => 3.46], $commission['7d']);
        $this->assertEquals(['gross' => 8.69, 'net' => 7.46], $commission['30d']);
    }

    public function test_upcoming_events_include_live_occurrences_in_next_seven_days_ranked_by_attendees(): void
    {
        $smallEventId = $this->createEvent('Small upcoming', status: 'LIVE');
        $bigEventId = $this->createEvent('Big upcoming', status: 'LIVE');
        $draftEventId = $this->createEvent('Draft upcoming');
        $farEventId = $this->createEvent('Far upcoming', status: 'LIVE');

        $this->createOccurrence($smallEventId, now()->addDays(2), attendees: 10);
        $this->createOccurrence($bigEventId, now()->addDays(5), attendees: 90000);
        $this->createOccurrence($draftEventId, now()->addDay(), attendees: 50);
        $this->createOccurrence($farEventId, now()->addDays(10), attendees: 50);

        $upcomingIds = collect($this->handle(AdminDashboardPeriod::LAST_7_DAYS, limit: 50)->upcoming_events)->pluck('id')->all();

        $this->assertContains($smallEventId, $upcomingIds);
        $this->assertContains($bigEventId, $upcomingIds);
        $this->assertNotContains($draftEventId, $upcomingIds);
        $this->assertNotContains($farEventId, $upcomingIds);
        $this->assertLessThan(array_search($smallEventId, $upcomingIds, true), array_search($bigEventId, $upcomingIds, true));
    }

    public function test_top_accounts_by_commission_are_limited_to_period(): void
    {
        $eventId = $this->createEvent('Commission account event');
        $this->createPlatformFee($this->createOrder($eventId, now()->subDays(2), 100.00), now()->subDays(2), gross: 3.00, net: 2.50);
        $this->createPlatformFee($this->createOrder($eventId, now()->subDays(4), 100.00), now()->subDays(4), gross: 2.00, net: null);
        $this->createPlatformFee($this->createOrder($eventId, now()->subDays(20), 100.00), now()->subDays(20), gross: 50.00, net: 40.00);

        $row = collect($this->handle(AdminDashboardPeriod::LAST_7_DAYS)->top_accounts_by_commission)
            ->first(fn (array $row) => $row['id'] === $this->accountId && $row['currency'] === self::TEST_CURRENCY);

        $this->assertNotNull($row);
        $this->assertSame(2, $row['orders_count']);
        $this->assertEquals(5.00, $row['gross']);
        $this->assertEquals(4.50, $row['net']);
    }

    public function test_top_accounts_by_commission_are_ranked_within_each_currency(): void
    {
        $eventId = $this->createEvent('Big currency event');
        $otherEventId = $this->createEvent('Small currency event', currency: 'ZZY');
        $this->createPlatformFee($this->createOrder($eventId, now()->subHour(), 100.00), now()->subHour(), gross: 999999.00, net: 999999.00);
        $this->createPlatformFee($this->createOrder($otherEventId, now()->subHour(), 1.00, currency: 'ZZY'), now()->subHour(), gross: 0.01, net: 0.01, currency: 'ZZY');

        $currencies = collect($this->handle(AdminDashboardPeriod::LAST_24_HOURS, limit: 1)->top_accounts_by_commission)
            ->pluck('currency')
            ->all();

        $this->assertContains(self::TEST_CURRENCY, $currencies);
        $this->assertContains('ZZY', $currencies);
    }

    public function test_attention_counts_only_items_awaiting_action(): void
    {
        $before = $this->handle(AdminDashboardPeriod::LAST_7_DAYS)->attention;

        $pendingEventId = $this->createEvent('Pending review', status: 'PENDING_MANUAL_REVIEW');
        $approvedEventId = $this->createEvent('Already live', status: 'LIVE');
        $this->createSpamCheck($pendingEventId, 'FLAGGED');
        $this->createSpamCheck($approvedEventId, 'FLAGGED');
        $this->createSpamCheck($pendingEventId, 'APPROVED');

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
            'failed_at' => now(),
        ]);

        DB::table('account_deletion_requests')->insert([
            'account_id' => $this->accountId,
            'requested_by_user_id' => $this->userId,
            'initiated_by' => 'USER',
            'status' => 'REQUESTED',
            'expected_outcome' => 'HARD_DELETE',
            'scheduled_deletion_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $after = $this->handle(AdminDashboardPeriod::LAST_7_DAYS)->attention;

        $this->assertSame($before['flagged_events'] + 1, $after['flagged_events']);
        $this->assertSame($before['failed_jobs'] + 1, $after['failed_jobs']);
        $this->assertSame($before['pending_deletion_requests'] + 1, $after['pending_deletion_requests']);
        $this->assertSame($before['messages_pending_review'], $after['messages_pending_review']);
    }

    private function createSpamCheck(int $eventId, string $status): void
    {
        DB::table('event_spam_checks')->insert([
            'event_id' => $eventId,
            'status' => $status,
            'content_hash' => hash('sha256', uniqid()),
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function handle(AdminDashboardPeriod $period, int $limit = 50): AdminDashboardResponseDTO
    {
        return $this->app->make(GetAdminDashboardDataHandler::class)->handle(
            new GetAdminDashboardDataDTO(period: $period, limit: $limit),
        );
    }

    private function viewsByEventId(AdminDashboardResponseDTO $result): array
    {
        return collect($result->most_viewed_events)->pluck('views', 'id')->all();
    }

    private function findEventRow(array $rows, int $eventId): array
    {
        $row = collect($rows)->firstWhere('id', $eventId);
        $this->assertNotNull($row, "Event {$eventId} missing from result");

        return $row;
    }

    private function createEvent(string $title, string $status = 'DRAFT', string $currency = self::TEST_CURRENCY): int
    {
        return DB::table('events')->insertGetId([
            'title' => $title,
            'status' => $status,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => $currency,
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => now()->subYear(),
            'updated_at' => now(),
        ]);
    }

    private function createDailyStatistic(int $eventId, CarbonInterface $date, int $views): void
    {
        DB::table('event_daily_statistics')->insert([
            'event_id' => $eventId,
            'date' => $date->toDateString(),
            'total_views' => $views,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(
        int $eventId,
        CarbonInterface $createdAt,
        float $totalGross,
        string $status = 'COMPLETED',
        float $totalRefunded = 0,
        string $currency = self::TEST_CURRENCY,
    ): int {
        return DB::table('orders')->insertGetId([
            'event_id' => $eventId,
            'short_id' => 'o_'.uniqid(),
            'public_id' => 'O-'.uniqid(),
            'status' => $status,
            'currency' => $currency,
            'total_gross' => $totalGross,
            'total_refunded' => $totalRefunded,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createPlatformFee(int $orderId, CarbonInterface $createdAt, float $gross, ?float $net, string $currency = self::TEST_CURRENCY): void
    {
        DB::table('order_payment_platform_fees')->insert([
            'order_id' => $orderId,
            'payment_platform' => 'STRIPE',
            'payment_platform_fee_amount' => 0,
            'application_fee_gross_amount' => $gross,
            'application_fee_net_amount' => $net,
            'currency' => $currency,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createOccurrence(int $eventId, CarbonInterface $startDate, int $attendees): void
    {
        $occurrenceId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addHours(2),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_occurrence_statistics')->insert([
            'event_id' => $eventId,
            'event_occurrence_id' => $occurrenceId,
            'attendees_registered' => $attendees,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
