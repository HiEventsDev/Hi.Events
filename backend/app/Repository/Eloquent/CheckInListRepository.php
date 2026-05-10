<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\CheckInList;
use HiEvents\Repository\DTO\CheckedInAttendeesCountDTO;
use HiEvents\Repository\DTO\CheckInListProductStatDTO;
use HiEvents\Repository\DTO\CheckInListRecentCheckInDTO;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CheckInListDomainObject>
 */
class CheckInListRepository extends BaseRepository implements CheckInListRepositoryInterface
{
    protected function getModel(): string
    {
        return CheckInList::class;
    }

    public function getDomainObject(): string
    {
        return CheckInListDomainObject::class;
    }

    public function getCheckedInAttendeeCountById(
        int $checkInListId,
        ?int $eventOccurrenceIdOverride = null,
    ): CheckedInAttendeesCountDTO {
        $clause = $this->buildOccurrenceFilterClauses($eventOccurrenceIdOverride);

        // "Empty attachments = all tickets": valid_attendees joins the list via
        // event_id and uses EXISTS/NOT EXISTS to express "attached, or list has
        // no attachments".
        $sql = <<<SQL
            WITH valid_check_ins AS (
                SELECT attendee_id, check_in_list_id
                FROM attendee_check_ins aci
                JOIN check_in_lists cil ON aci.check_in_list_id = cil.id
                WHERE aci.deleted_at IS NULL
                AND aci.check_in_list_id = :check_in_list_id
                {$clause->checkInClause}
                GROUP BY attendee_id, check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, cil.id AS check_in_list_id
                     FROM attendees a
                                 JOIN orders o ON a.order_id = o.id
                                 JOIN check_in_lists cil ON cil.event_id = a.event_id
                                      AND cil.id = :check_in_list_id
                                      AND cil.deleted_at IS NULL
                                 JOIN event_settings es ON cil.event_id = es.event_id
                     WHERE a.deleted_at IS NULL
                        {$clause->attendeeClause}
                        AND (
                            EXISTS (
                                SELECT 1 FROM product_check_in_lists pcil
                                WHERE pcil.check_in_list_id = cil.id
                                  AND pcil.product_id = a.product_id
                                  AND pcil.deleted_at IS NULL
                            )
                            OR NOT EXISTS (
                                SELECT 1 FROM product_check_in_lists pcil
                                WHERE pcil.check_in_list_id = cil.id
                                  AND pcil.deleted_at IS NULL
                            )
                        )
                        AND (
                            (es.allow_orders_awaiting_offline_payment_to_check_in = true AND a.status in ('ACTIVE', 'AWAITING_PAYMENT') AND o.status IN ('COMPLETED', 'AWAITING_OFFLINE_PAYMENT'))
                            OR
                            (es.allow_orders_awaiting_offline_payment_to_check_in = false AND a.status = 'ACTIVE' AND o.status = 'COMPLETED')
                        )
                 )
            SELECT
                cil.id AS check_in_list_id,
                COUNT(va.id) AS total_attendees,
                COUNT(DISTINCT vci.attendee_id) AS checked_in_attendees
            FROM check_in_lists cil
                     LEFT JOIN valid_attendees va ON va.check_in_list_id = cil.id
                     LEFT JOIN valid_check_ins vci ON vci.attendee_id = va.id AND vci.check_in_list_id = va.check_in_list_id
            WHERE cil.id = :check_in_list_id
              AND cil.deleted_at IS NULL
            GROUP BY cil.id;
        SQL;

        $query = $this->db->selectOne(
            $sql,
            array_merge(['check_in_list_id' => $checkInListId], $clause->bindings),
        );

        return new CheckedInAttendeesCountDTO(
            checkInListId: $checkInListId,
            checkedInCount: $query->checked_in_attendees ?? 0,
            totalAttendeesCount: $query->total_attendees ?? 0,
        );
    }

    /**
     * Build the WHERE fragments and bindings that restrict stats queries to a
     * specific event occurrence.
     *
     * - Override set: count only attendees/check-ins with matching event_occurrence_id.
     * - Override null: auto-scope to the check-in list's own event_occurrence_id if
     *   set; otherwise count across all occurrences (unscoped "All occurrences" list).
     */
    private function buildOccurrenceFilterClauses(?int $override): object
    {
        if ($override !== null) {
            return (object) [
                'attendeeClause' => 'AND a.event_occurrence_id = :occurrence_id',
                'checkInClause' => 'AND aci.event_occurrence_id = :occurrence_id',
                'bindings' => ['occurrence_id' => $override],
            ];
        }

        // Auto-scope to the list's own occurrence when set. A null on the list
        // means "All occurrences" — no row-level filter.
        return (object) [
            'attendeeClause' => 'AND (cil.event_occurrence_id IS NULL OR a.event_occurrence_id = cil.event_occurrence_id)',
            'checkInClause' => 'AND (cil.event_occurrence_id IS NULL OR aci.event_occurrence_id = cil.event_occurrence_id)',
            'bindings' => [],
        ];
    }

    public function getCheckedInAttendeeCountByIds(array $checkInListIds): Collection
    {
        $placeholders = implode(',', array_fill(0, count($checkInListIds), '?'));

        // Bulk version: auto-scopes each list via cil.event_occurrence_id (no
        // single override). Same "empty attachments = all tickets" rule applies.
        $sql = <<<SQL
            WITH valid_check_ins AS (
                SELECT aci.attendee_id, aci.check_in_list_id
                FROM attendee_check_ins aci
                JOIN check_in_lists cil ON aci.check_in_list_id = cil.id
                WHERE aci.deleted_at IS NULL
                AND aci.check_in_list_id IN ($placeholders)
                AND (cil.event_occurrence_id IS NULL OR aci.event_occurrence_id = cil.event_occurrence_id)
                GROUP BY aci.attendee_id, aci.check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, cil.id AS check_in_list_id
                     FROM attendees a
                              JOIN orders o ON a.order_id = o.id
                              JOIN check_in_lists cil ON cil.event_id = a.event_id
                                   AND cil.id IN ($placeholders)
                                   AND cil.deleted_at IS NULL
                              JOIN event_settings es ON cil.event_id = es.event_id
                     WHERE a.deleted_at IS NULL
                       AND (cil.event_occurrence_id IS NULL OR a.event_occurrence_id = cil.event_occurrence_id)
                       AND (
                           EXISTS (
                               SELECT 1 FROM product_check_in_lists pcil
                               WHERE pcil.check_in_list_id = cil.id
                                 AND pcil.product_id = a.product_id
                                 AND pcil.deleted_at IS NULL
                           )
                           OR NOT EXISTS (
                               SELECT 1 FROM product_check_in_lists pcil
                               WHERE pcil.check_in_list_id = cil.id
                                 AND pcil.deleted_at IS NULL
                           )
                       )
                       AND (
                           (es.allow_orders_awaiting_offline_payment_to_check_in = true AND a.status IN ('ACTIVE', 'AWAITING_PAYMENT') AND o.status IN ('COMPLETED', 'AWAITING_OFFLINE_PAYMENT'))
                           OR
                           (es.allow_orders_awaiting_offline_payment_to_check_in = false AND a.status = 'ACTIVE' AND o.status = 'COMPLETED')
                       )
                 )
            SELECT
                cil.id AS check_in_list_id,
                COUNT(va.id) AS total_attendees,
                COUNT(DISTINCT vci.attendee_id) AS checked_in_attendees
            FROM check_in_lists cil
                     LEFT JOIN valid_attendees va ON va.check_in_list_id = cil.id
                     LEFT JOIN valid_check_ins vci ON vci.attendee_id = va.id AND vci.check_in_list_id = va.check_in_list_id
            WHERE cil.id IN ($placeholders)
              AND cil.deleted_at IS NULL
            GROUP BY cil.id;
    SQL;

        $query = $this->db->select($sql, array_merge($checkInListIds, $checkInListIds, $checkInListIds));

        return collect($query)->map(
            static fn($item) => new CheckedInAttendeesCountDTO(
                checkInListId: $item->check_in_list_id,
                checkedInCount: $item->checked_in_attendees,
                totalAttendeesCount: $item->total_attendees,
            )
        );
    }

    public function getPerProductCheckInStatsById(
        int $checkInListId,
        ?int $eventOccurrenceIdOverride = null,
    ): Collection {
        $clause = $this->buildOccurrenceFilterClauses($eventOccurrenceIdOverride);

        // For the product breakdown, "empty attachments" returns a row for every
        // product on the event.
        $sql = <<<SQL
            WITH valid_check_ins AS (
                SELECT aci.attendee_id, aci.check_in_list_id
                FROM attendee_check_ins aci
                JOIN check_in_lists cil ON aci.check_in_list_id = cil.id
                WHERE aci.deleted_at IS NULL
                  AND aci.check_in_list_id = :check_in_list_id
                  {$clause->checkInClause}
                GROUP BY aci.attendee_id, aci.check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, a.product_id, cil.id AS check_in_list_id
                     FROM attendees a
                              JOIN orders o ON a.order_id = o.id
                              JOIN check_in_lists cil ON cil.event_id = a.event_id
                                   AND cil.id = :check_in_list_id
                                   AND cil.deleted_at IS NULL
                              JOIN event_settings es ON cil.event_id = es.event_id
                     WHERE a.deleted_at IS NULL
                       {$clause->attendeeClause}
                       AND (
                           EXISTS (
                               SELECT 1 FROM product_check_in_lists pcil
                               WHERE pcil.check_in_list_id = cil.id
                                 AND pcil.product_id = a.product_id
                                 AND pcil.deleted_at IS NULL
                           )
                           OR NOT EXISTS (
                               SELECT 1 FROM product_check_in_lists pcil
                               WHERE pcil.check_in_list_id = cil.id
                                 AND pcil.deleted_at IS NULL
                           )
                       )
                       AND (
                           (es.allow_orders_awaiting_offline_payment_to_check_in = true AND a.status IN ('ACTIVE', 'AWAITING_PAYMENT') AND o.status IN ('COMPLETED', 'AWAITING_OFFLINE_PAYMENT'))
                           OR
                           (es.allow_orders_awaiting_offline_payment_to_check_in = false AND a.status = 'ACTIVE' AND o.status = 'COMPLETED')
                       )
                 )
            SELECT
                p.id AS product_id,
                p.title AS product_title,
                COUNT(va.id) AS total_attendees,
                COUNT(DISTINCT vci.attendee_id) AS checked_in_attendees
            FROM products p
                     JOIN check_in_lists cil ON cil.id = :check_in_list_id
                     LEFT JOIN valid_attendees va ON va.product_id = p.id
                     LEFT JOIN valid_check_ins vci ON vci.attendee_id = va.id
            WHERE p.deleted_at IS NULL
              AND (
                  EXISTS (
                      SELECT 1 FROM product_check_in_lists pcil
                      WHERE pcil.check_in_list_id = cil.id
                        AND pcil.product_id = p.id
                        AND pcil.deleted_at IS NULL
                  )
                  OR (
                      p.event_id = cil.event_id
                      AND NOT EXISTS (
                          SELECT 1 FROM product_check_in_lists pcil
                          WHERE pcil.check_in_list_id = cil.id
                            AND pcil.deleted_at IS NULL
                      )
                  )
              )
            GROUP BY p.id, p.title
            ORDER BY p.title;
        SQL;

        $rows = $this->db->select(
            $sql,
            array_merge(['check_in_list_id' => $checkInListId], $clause->bindings),
        );

        return collect($rows)->map(
            static fn($row) => new CheckInListProductStatDTO(
                productId: (int)$row->product_id,
                productTitle: $row->product_title,
                totalAttendees: (int)$row->total_attendees,
                checkedInAttendees: (int)$row->checked_in_attendees,
            )
        );
    }

    public function getRecentCheckInsById(
        int $checkInListId,
        int $limit,
        ?int $eventOccurrenceIdOverride = null,
    ): Collection {
        $clause = $this->buildOccurrenceFilterClauses($eventOccurrenceIdOverride);

        $sql = <<<SQL
            SELECT
                a.public_id AS attendee_public_id,
                a.first_name,
                a.last_name,
                p.title AS product_title,
                aci.created_at AS checked_in_at
            FROM attendee_check_ins aci
                     JOIN check_in_lists cil ON aci.check_in_list_id = cil.id
                     JOIN attendees a ON a.id = aci.attendee_id
                     LEFT JOIN products p ON p.id = a.product_id
            WHERE aci.check_in_list_id = :check_in_list_id
              AND aci.deleted_at IS NULL
              AND a.deleted_at IS NULL
              {$clause->checkInClause}
            ORDER BY aci.created_at DESC
            LIMIT :row_limit;
        SQL;

        $rows = $this->db->select($sql, array_merge([
            'check_in_list_id' => $checkInListId,
            'row_limit' => $limit,
        ], $clause->bindings));

        return collect($rows)->map(
            static fn($row) => new CheckInListRecentCheckInDTO(
                attendeePublicId: $row->attendee_public_id,
                firstName: $row->first_name ?? '',
                lastName: $row->last_name ?? '',
                productTitle: $row->product_title,
                checkedInAt: (string)$row->checked_in_at,
            )
        );
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [CheckInListDomainObjectAbstract::EVENT_ID, '=', $eventId]
        ];

        if (!empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(CapacityAssignmentDomainObjectAbstract::NAME, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            $this->validateSortColumn($params->sort_by, CheckInListDomainObject::class),
            $this->validateSortDirection($params->sort_direction, CheckInListDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }
}
