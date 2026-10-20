<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use Carbon\Carbon;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerSettingDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Models\Organizer;
use HiEvents\Repository\DTO\Organizer\OrganizerDailyStatsResponseDTO;
use HiEvents\Repository\DTO\Organizer\OrganizerStatsResponseDTO;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<OrganizerDomainObject>
 */
class OrganizerRepository extends BaseRepository implements OrganizerRepositoryInterface
{
    protected function getModel(): string
    {
        return Organizer::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerDomainObject::class;
    }

    public function getSitemapOrganizers(int $page, int $perPage): LengthAwarePaginator
    {
        return $this->handleResults($this->model
            ->select([
                'organizers.'.OrganizerDomainObjectAbstract::ID,
                'organizers.'.OrganizerDomainObjectAbstract::NAME,
                'organizers.'.OrganizerDomainObjectAbstract::UPDATED_AT,
            ])
            ->join('organizer_settings', 'organizers.id', '=', 'organizer_settings.organizer_id')
            ->where('organizers.'.OrganizerDomainObjectAbstract::STATUS, OrganizerStatus::LIVE->name)
            ->where('organizer_settings.'.OrganizerSettingDomainObjectAbstract::ALLOW_SEARCH_ENGINE_INDEXING, true)
            ->whereNull('organizers.'.OrganizerDomainObjectAbstract::DELETED_AT)
            ->orderBy('organizers.'.OrganizerDomainObjectAbstract::ID)
            ->paginate($perPage, ['*'], 'page', $page));
    }

    public function getSitemapOrganizerCount(): int
    {
        return $this->model
            ->newQuery()
            ->join('organizer_settings', 'organizers.id', '=', 'organizer_settings.organizer_id')
            ->where('organizers.'.OrganizerDomainObjectAbstract::STATUS, OrganizerStatus::LIVE->name)
            ->where('organizer_settings.'.OrganizerSettingDomainObjectAbstract::ALLOW_SEARCH_ENGINE_INDEXING, true)
            ->whereNull('organizers.'.OrganizerDomainObjectAbstract::DELETED_AT)
            ->count();
    }

    public function getOrganizerStats(
        int $organizerId,
        int $accountId,
        string $currencyCode,
        string $startDate,
        string $endDate,
    ): OrganizerStatsResponseDTO {
        $totalsQuery = <<<'SQL'
        SELECT
            COALESCE(SUM(eods.products_sold), 0) AS total_products_sold,
            COALESCE(SUM(eods.orders_created), 0) AS total_orders,
            COALESCE(SUM(eods.sales_total_gross), 0) AS total_gross_sales,
            COALESCE(SUM(eods.total_tax), 0) AS total_tax,
            COALESCE(SUM(eods.total_fee), 0) AS total_fees,
            COALESCE(SUM(eods.total_refunded), 0) AS total_refunded,
            COALESCE(SUM(eods.attendees_registered), 0) AS attendees_registered
        FROM event_occurrence_daily_statistics eods
        JOIN events e ON e.id = eods.event_id
        WHERE e.organizer_id = :organizerId
          AND e.account_id = :accountId
          AND e.currency = :currencyCode
          AND eods.date >= :startDate::date
          AND eods.date <= :endDate::date
          AND eods.deleted_at IS NULL
          AND e.deleted_at IS NULL;
SQL;

        $totalsResult = $this->db->selectOne($totalsQuery, [
            'organizerId' => $organizerId,
            'accountId' => $accountId,
            'currencyCode' => $currencyCode,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $totalViewsQuery = <<<'SQL'
        SELECT COALESCE(SUM(es.total_views), 0) AS total_views
        FROM event_statistics es
        JOIN events e ON e.id = es.event_id
        WHERE e.organizer_id = :organizerId
          AND e.account_id = :accountId
          AND e.currency = :currencyCode
          AND es.deleted_at IS NULL
          AND e.deleted_at IS NULL;
SQL;

        $totalViewsResult = $this->db->selectOne($totalViewsQuery, [
            'organizerId' => $organizerId,
            'accountId' => $accountId,
            'currencyCode' => $currencyCode,
        ]);

        $allOrganizersCurrenciesQuery = <<<'SQL'
        SELECT DISTINCT e.currency FROM events e
        WHERE e.organizer_id = :organizerId
          AND e.account_id = :accountId
          AND e.deleted_at IS NULL;
SQL;

        $allOrganizersCurrencies = $this->db->select($allOrganizersCurrenciesQuery, [
            'organizerId' => $organizerId,
            'accountId' => $accountId,
        ]);

        $dailyStats = $this->getDailyOrganizerStats(
            organizerId: $organizerId,
            accountId: $accountId,
            currencyCode: $currencyCode,
            startDate: $startDate,
            endDate: $endDate,
        );

        return new OrganizerStatsResponseDTO(
            total_products_sold: (int) ($totalsResult->total_products_sold ?? 0),
            total_attendees_registered: (int) ($totalsResult->attendees_registered ?? 0),
            total_orders: (int) ($totalsResult->total_orders ?? 0),
            total_gross_sales: (float) ($totalsResult->total_gross_sales ?? 0),
            total_fees: (float) ($totalsResult->total_fees ?? 0),
            total_tax: (float) ($totalsResult->total_tax ?? 0),
            total_views: (int) ($totalViewsResult->total_views ?? 0),
            total_refunded: (float) ($totalsResult->total_refunded ?? 0),
            currency_code: $currencyCode,
            daily_stats: $dailyStats,
            start_date: $startDate,
            end_date: $endDate,
            all_organizers_currencies: array_map(
                static fn ($currency) => $currency->currency,
                $allOrganizersCurrencies
            ),
        );
    }

    private function getDailyOrganizerStats(
        int $organizerId,
        int $accountId,
        string $currencyCode,
        string $startDate,
        string $endDate,
    ): Collection {
        $query = <<<'SQL'
            WITH date_series AS (
              SELECT date::date
              FROM generate_series(
                :startDate::date,
                :endDate::date,
                '1 day'
              ) AS gs(date)
            )
            SELECT
              ds.date,
              COALESCE(SUM(eods.attendees_registered), 0) AS attendees_registered,
              COALESCE(SUM(eods.products_sold), 0) AS products_sold,
              COALESCE(SUM(eods.sales_total_gross), 0) AS total_sales_gross,
              COALESCE(SUM(eods.orders_created), 0) AS orders_created,
              COALESCE(SUM(eods.total_refunded), 0) AS total_refunded
            FROM date_series ds
            LEFT JOIN event_occurrence_daily_statistics eods
              ON ds.date = eods.date
              AND eods.deleted_at IS NULL
              AND eods.event_id IN (
                SELECT e.id FROM events e
                WHERE e.organizer_id = :organizerId
                  AND e.account_id = :accountId
                  AND e.currency = :currencyCode
                  AND e.deleted_at IS NULL
              )
            GROUP BY ds.date
            ORDER BY ds.date ASC;
SQL;

        $results = $this->db->select($query, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'organizerId' => $organizerId,
            'accountId' => $accountId,
            'currencyCode' => $currencyCode,
        ]);

        $currentTime = Carbon::now('UTC')->toTimeString();

        return collect($results)->map(function (object $result) use ($currentTime) {
            $dateTimeWithCurrentTime = (new Carbon($result->date))->setTimezone('UTC')->format('Y-m-d').' '.$currentTime;

            return new OrganizerDailyStatsResponseDTO(
                date: $dateTimeWithCurrentTime,
                attendees_registered: (int) $result->attendees_registered,
                products_sold: (int) $result->products_sold,
                total_sales_gross: (float) $result->total_sales_gross,
                orders_created: (int) $result->orders_created,
                total_refunded: (float) $result->total_refunded,
            );
        });
    }
}
