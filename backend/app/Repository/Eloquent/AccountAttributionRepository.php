<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountAttributionDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\AccountAttribution;
use HiEvents\Repository\Interfaces\AccountAttributionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountAttributionRepository extends BaseRepository implements AccountAttributionRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountAttribution::class;
    }

    public function getDomainObject(): string
    {
        return AccountAttributionDomainObject::class;
    }

    public function getAttributionStats(
        string $groupBy,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        int $page
    ): LengthAwarePaginator {
        $groupByMap = [
            'source' => 'utm_source',
            'campaign' => 'utm_campaign',
            'medium' => 'utm_medium',
            'source_type' => 'source_type',
        ];

        $groupColumn = $groupByMap[$groupBy] ?? 'utm_source';
        $liveStatus = EventStatus::LIVE->name;

        $query = DB::table('account_attributions as aa')
            ->select([
                DB::raw("COALESCE(aa.{$groupColumn}, '(not set)') as attribution_value"),
                DB::raw('COUNT(DISTINCT aa.account_id) as total_accounts'),
                DB::raw('COUNT(DISTINCT e.id) as total_events'),
                DB::raw("COUNT(DISTINCT CASE WHEN e.status = '{$liveStatus}' THEN e.id END) as live_events"),
                DB::raw('COUNT(DISTINCT CASE WHEN asp.stripe_setup_completed_at IS NOT NULL THEN aa.account_id END) as stripe_connected'),
                DB::raw('COUNT(DISTINCT CASE WHEN a.is_manually_verified = true THEN aa.account_id END) as verified_accounts'),
                DB::raw('COALESCE(SUM(es.sales_total_gross), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(es.orders_created), 0) as total_orders'),
            ])
            ->join('accounts as a', 'aa.account_id', '=', 'a.id')
            ->leftJoin('account_stripe_platforms as asp', function ($join) {
                $join->on('a.id', '=', 'asp.account_id')
                    ->whereNull('asp.deleted_at');
            })
            ->leftJoin('events as e', function ($join) {
                $join->on('a.id', '=', 'e.account_id')
                    ->whereNull('e.deleted_at');
            })
            ->leftJoin('event_statistics as es', 'e.id', '=', 'es.event_id')
            ->whereNull('a.deleted_at');

        if ($dateFrom) {
            $query->where('aa.created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('aa.created_at', '<=', $dateTo);
        }

        $query->groupBy(DB::raw("COALESCE(aa.{$groupColumn}, '(not set)')"))
            ->orderByDesc('total_accounts');

        return $query->paginate(
            perPage: min($perPage, $this->maxPerPage),
            page: $page
        );
    }

    public function getAttributionSummary(?string $dateFrom, ?string $dateTo): array
    {
        $attributedQuery = DB::table('account_attributions as aa')
            ->select([
                DB::raw("COUNT(DISTINCT CASE WHEN aa.source_type = 'paid' THEN aa.account_id END) as paid_accounts"),
                DB::raw("COUNT(DISTINCT CASE WHEN aa.source_type = 'organic' THEN aa.account_id END) as organic_accounts"),
                DB::raw("COUNT(DISTINCT CASE WHEN aa.source_type = 'referral' THEN aa.account_id END) as referral_accounts"),
                DB::raw('COUNT(DISTINCT aa.account_id) as attributed_accounts'),
            ]);

        if ($dateFrom) {
            $attributedQuery->where('aa.created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $attributedQuery->where('aa.created_at', '<=', $dateTo);
        }

        $attributed = $attributedQuery->first();

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
}
