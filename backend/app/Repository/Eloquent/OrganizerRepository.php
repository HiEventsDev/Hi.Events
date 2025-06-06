<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Models\Organizer;
use HiEvents\Repository\DTO\Organizer\OrganizerStatsResponseDTO;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

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

    public function getOrganizerStats(int $organizerId, int $accountId, string $currencyCode): OrganizerStatsResponseDTO
    {
        $totalsQuery = <<<SQL
        SELECT
            SUM(es.products_sold) AS total_products_sold,
            SUM(es.orders_created) AS total_orders,
            SUM(es.sales_total_gross) AS total_gross_sales,
            SUM(es.total_tax) AS total_tax,
            SUM(es.total_fee) AS total_fees,
            SUM(es.total_views) AS total_views,
            SUM(es.total_refunded) AS total_refunded,
            SUM(es.attendees_registered) AS attendees_registered
        FROM event_statistics es
        JOIN events e ON e.id = es.event_id
        WHERE e.organizer_id = :organizerId
          AND e.account_id = :accountId
          AND e.currency = :currencyCode
          AND es.deleted_at IS NULL;
    SQL;

        $totalsResult = $this->db->selectOne($totalsQuery, [
            'organizerId' => $organizerId,
            'accountId' => $accountId,
            'currencyCode' => $currencyCode,
        ]);

        $allOrganizersCurrenciesQuery = <<<SQL
        SELECT DISTINCT e.currency FROM events e
        WHERE e.organizer_id = :organizerId
          AND e.account_id = :accountId
          AND e.deleted_at IS NULL;
SQL;

        $allOrganizersCurrencies = $this->db->select($allOrganizersCurrenciesQuery, [
            'organizerId' => $organizerId,
            'accountId' => $accountId,
        ]);

        return new OrganizerStatsResponseDTO(
            total_products_sold: (int)($totalsResult->total_products_sold ?? 0),
            total_attendees_registered: (int)($totalsResult->attendees_registered ?? 0),
            total_orders: (int)($totalsResult->total_orders ?? 0),
            total_gross_sales: (float)($totalsResult->total_gross_sales ?? 0),
            total_fees: (float)($totalsResult->total_fees ?? 0),
            total_tax: (float)($totalsResult->total_tax ?? 0),
            total_views: (int)($totalsResult->total_views ?? 0),
            total_refunded: (float)($totalsResult->total_refunded ?? 0),
            currency_code: $currencyCode,
            all_organizers_currencies: array_map(
                static fn($currency) => $currency->currency,
                $allOrganizersCurrencies
            ),
        );
    }
}
