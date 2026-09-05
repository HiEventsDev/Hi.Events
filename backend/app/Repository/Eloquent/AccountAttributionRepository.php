<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountAttributionDomainObject;
use HiEvents\DomainObjects\Enums\AttributionGroupBy;
use HiEvents\DomainObjects\Enums\AttributionSourceType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\AccountAttribution;
use HiEvents\Repository\Interfaces\AccountAttributionRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<AccountAttributionDomainObject>
 */
class AccountAttributionRepository extends BaseRepository implements AccountAttributionRepositoryInterface
{
    private const NOT_SET = '(not set)';

    protected function getModel(): string
    {
        return AccountAttribution::class;
    }

    public function getDomainObject(): string
    {
        return AccountAttributionDomainObject::class;
    }

    public function getAttributionStats(
        AttributionGroupBy $groupBy,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        int $page
    ): LengthAwarePaginator {
        $groupExpression = $this->groupExpression($groupBy);
        $liveStatus = EventStatus::LIVE->name;

        $stats = $this->attributedAccountsQuery($dateFrom, $dateTo)
            ->select([
                DB::raw("{$groupExpression} as attribution_value"),
                DB::raw('COUNT(DISTINCT aa.account_id) as total_accounts'),
                DB::raw('COUNT(DISTINCT e.id) as total_events'),
                DB::raw("COUNT(DISTINCT CASE WHEN e.status = '{$liveStatus}' THEN e.id END) as live_events"),
                DB::raw('COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM organizers o2 JOIN organizer_stripe_platforms osp2 ON osp2.organizer_id = o2.id WHERE o2.account_id = aa.account_id AND o2.deleted_at IS NULL AND osp2.deleted_at IS NULL AND osp2.stripe_setup_completed_at IS NOT NULL) THEN aa.account_id END) as stripe_connected'),
                DB::raw('COUNT(DISTINCT CASE WHEN a.is_manually_verified = true THEN aa.account_id END) as verified_accounts'),
                DB::raw('COALESCE(SUM(es.orders_created), 0) as total_orders'),
            ])
            ->leftJoin('events as e', function ($join) {
                $join->on('a.id', '=', 'e.account_id')
                    ->whereNull('e.deleted_at');
            })
            ->leftJoin('event_statistics as es', function ($join) {
                $join->on('e.id', '=', 'es.event_id')
                    ->whereNull('es.deleted_at');
            })
            ->groupBy(DB::raw($groupExpression))
            ->orderByDesc('total_accounts')
            ->orderBy('attribution_value')
            ->paginate(
                perPage: min($perPage, $this->maxPerPage),
                page: $page
            );

        $revenueByValue = $this->revenueByCurrency(
            groupExpression: $groupExpression,
            attributionValues: $stats->getCollection()->pluck('attribution_value')->all(),
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );

        return $stats->through(function (object $row) use ($revenueByValue) {
            $row->revenue_by_currency = (object) ($revenueByValue[$row->attribution_value] ?? []);

            return $row;
        });
    }

    public function getAttributionSummary(?string $dateFrom, ?string $dateTo): array
    {
        $attributed = $this->attributedAccountsQuery($dateFrom, $dateTo)
            ->select([
                DB::raw($this->countBySourceType(AttributionSourceType::PAID, 'paid_accounts')),
                DB::raw($this->countBySourceType(AttributionSourceType::ORGANIC, 'organic_accounts')),
                DB::raw($this->countBySourceType(AttributionSourceType::REFERRAL, 'referral_accounts')),
                DB::raw('COUNT(DISTINCT aa.account_id) as attributed_accounts'),
            ])
            ->first();

        $totalQuery = DB::table('accounts')
            ->whereNull('deleted_at');

        if ($dateFrom) {
            $totalQuery->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $totalQuery->where('created_at', '<=', $dateTo);
        }

        $totalAccounts = $totalQuery->count();
        $attributedCount = (int) $attributed->attributed_accounts;

        return [
            'paid_accounts' => (int) $attributed->paid_accounts,
            'organic_accounts' => (int) $attributed->organic_accounts,
            'referral_accounts' => (int) $attributed->referral_accounts,
            'attributed_accounts' => $attributedCount,
            'unattributed_accounts' => $totalAccounts - $attributedCount,
            'total_accounts' => $totalAccounts,
        ];
    }

    private function attributedAccountsQuery(?string $dateFrom, ?string $dateTo): Builder
    {
        $query = DB::table('account_attributions as aa')
            ->join('accounts as a', 'aa.account_id', '=', 'a.id')
            ->whereNull('a.deleted_at');

        if ($dateFrom) {
            $query->where('a.created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('a.created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function revenueByCurrency(
        string $groupExpression,
        array $attributionValues,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        if ($attributionValues === []) {
            return [];
        }

        $rows = $this->attributedAccountsQuery($dateFrom, $dateTo)
            ->select([
                DB::raw("{$groupExpression} as attribution_value"),
                'e.currency',
                DB::raw('SUM(es.sales_total_gross) as revenue'),
            ])
            ->join('events as e', function ($join) {
                $join->on('a.id', '=', 'e.account_id')
                    ->whereNull('e.deleted_at');
            })
            ->join('event_statistics as es', function ($join) {
                $join->on('e.id', '=', 'es.event_id')
                    ->whereNull('es.deleted_at');
            })
            ->whereIn(DB::raw($groupExpression), $attributionValues)
            ->groupBy(DB::raw($groupExpression), 'e.currency')
            ->having(DB::raw('SUM(es.sales_total_gross)'), '>', 0)
            ->get();

        $revenue = [];

        foreach ($rows as $row) {
            $revenue[$row->attribution_value][$row->currency] = (float) $row->revenue;
        }

        return $revenue;
    }

    private function groupExpression(AttributionGroupBy $groupBy): string
    {
        $column = match ($groupBy) {
            AttributionGroupBy::SOURCE => 'aa.utm_source',
            AttributionGroupBy::MEDIUM => 'aa.utm_medium',
            AttributionGroupBy::CAMPAIGN => 'aa.utm_campaign',
            AttributionGroupBy::CONTENT => 'aa.utm_content',
            AttributionGroupBy::TERM => 'aa.utm_term',
            AttributionGroupBy::CTA => "aa.utm_raw->>'ref'",
            AttributionGroupBy::SOURCE_TYPE => 'aa.source_type',
        };

        return sprintf("COALESCE(%s, '%s')", $column, self::NOT_SET);
    }

    private function countBySourceType(AttributionSourceType $type, string $alias): string
    {
        return sprintf("COUNT(DISTINCT CASE WHEN aa.source_type = '%s' THEN aa.account_id END) as %s", $type->value, $alias);
    }
}
