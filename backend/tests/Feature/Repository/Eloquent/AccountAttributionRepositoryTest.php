<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\AttributionGroupBy;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\AccountAttributionRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountAttributionRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private const WINDOW_START = '2019-06-01 00:00:00';

    private const WINDOW_END = '2019-06-01 23:59:59';

    private AccountAttributionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(AccountAttributionRepository::class);
    }

    public function test_revenue_is_split_by_currency(): void
    {
        $accountId = $this->createAttributedAccount('google', 'paid');
        $this->createEventWithRevenue($accountId, 'USD', 100.00);
        $this->createEventWithRevenue($accountId, 'EUR', 40.50);
        $this->createEventWithRevenue($accountId, 'GBP', 0);

        $row = $this->statsRow(AttributionGroupBy::SOURCE, 'google');

        $this->assertSame(1, (int) $row->total_accounts);
        $this->assertSame(3, (int) $row->total_events);
        $this->assertEquals(['USD' => 100.0, 'EUR' => 40.5], (array) $row->revenue_by_currency);
    }

    public function test_accounts_without_revenue_have_empty_revenue_map(): void
    {
        $this->createAttributedAccount('newsletter', 'organic');

        $this->assertSame([], (array) $this->statsRow(AttributionGroupBy::SOURCE, 'newsletter')->revenue_by_currency);
    }

    public function test_cta_grouping_reads_ref_from_utm_raw(): void
    {
        $this->createAttributedAccount('hi.events', 'organic', ['ref' => 'hero-cta']);
        $this->createAttributedAccount('hi.events', 'organic', ['ref' => 'hero-cta']);
        $this->createAttributedAccount('hi.events', 'organic');

        $this->assertSame(2, (int) $this->statsRow(AttributionGroupBy::CTA, 'hero-cta')->total_accounts);
        $this->assertSame(1, (int) $this->statsRow(AttributionGroupBy::CTA, '(not set)')->total_accounts);
    }

    public function test_soft_deleted_accounts_are_excluded_from_stats_and_summary(): void
    {
        $liveAccountId = $this->createAttributedAccount('podcast', 'referral');
        $deletedAccountId = $this->createAttributedAccount('podcast', 'referral');
        DB::table('accounts')->where('id', $deletedAccountId)->update(['deleted_at' => now()]);

        $this->assertSame(1, (int) $this->statsRow(AttributionGroupBy::SOURCE, 'podcast')->total_accounts);

        $summary = $this->repository->getAttributionSummary(dateFrom: self::WINDOW_START, dateTo: self::WINDOW_END);

        $this->assertSame(1, $summary['total_accounts']);
        $this->assertSame(1, $summary['attributed_accounts']);
        $this->assertSame(1, $summary['referral_accounts']);
        $this->assertSame(0, $summary['unattributed_accounts']);
        $this->assertSame($liveAccountId, (int) DB::table('accounts')->where('created_at', '2019-06-01 12:00:00')->whereNull('deleted_at')->value('id'));
    }

    private function statsRow(AttributionGroupBy $groupBy, string $value): object
    {
        $stats = $this->repository->getAttributionStats(
            groupBy: $groupBy,
            dateFrom: self::WINDOW_START,
            dateTo: self::WINDOW_END,
            perPage: 100,
            page: 1,
        );

        return $stats->getCollection()->firstWhere('attribution_value', $value);
    }

    private function createAttributedAccount(string $utmSource, string $sourceType, ?array $utmRaw = null): int
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        DB::table('accounts')->where('id', $accountId)->update(['created_at' => '2019-06-01 12:00:00']);

        DB::table('account_attributions')->insert([
            'account_id' => $accountId,
            'utm_source' => $utmSource,
            'source_type' => $sourceType,
            'utm_raw' => $utmRaw === null ? null : json_encode($utmRaw),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $accountId;
    }

    private function createEventWithRevenue(int $accountId, string $currency, float $revenue): void
    {
        $user = DB::table('account_users')->where('account_id', $accountId)->value('user_id');

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Organizer',
            'email' => 'organizer@example.test',
            'currency' => $currency,
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = DB::table('events')->insertGetId([
            'title' => 'Event',
            'account_id' => $accountId,
            'user_id' => $user,
            'organizer_id' => $organizerId,
            'currency' => $currency,
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'status' => EventStatus::LIVE->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_statistics')->insert([
            'event_id' => $eventId,
            'sales_total_gross' => $revenue,
            'orders_created' => $revenue > 0 ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
