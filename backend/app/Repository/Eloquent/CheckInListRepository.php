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

    public function getCheckedInAttendeeCountById(int $checkInListId): CheckedInAttendeesCountDTO
    {
        $sql = <<<SQL
            WITH valid_check_ins AS (
                SELECT attendee_id, check_in_list_id
                FROM attendee_check_ins
                WHERE deleted_at IS NULL
                AND check_in_list_id = :check_in_list_id
                GROUP BY attendee_id, check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, pcil.check_in_list_id
                     FROM attendees a
                                 JOIN product_check_in_lists pcil ON a.product_id = pcil.product_id
                                 JOIN orders o ON a.order_id = o.id
                                 JOIN check_in_lists cil ON pcil.check_in_list_id = cil.id
                                 JOIN event_settings es ON cil.event_id = es.event_id
                     WHERE a.deleted_at IS NULL
                        AND pcil.deleted_at IS NULL
                        AND pcil.check_in_list_id = :check_in_list_id
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

        $query = $this->db->selectOne($sql, ['check_in_list_id' => $checkInListId]);

        return new CheckedInAttendeesCountDTO(
            checkInListId: $checkInListId,
            checkedInCount: $query->checked_in_attendees ?? 0,
            totalAttendeesCount: $query->total_attendees ?? 0,
        );
    }

    public function getCheckedInAttendeeCountByIds(array $checkInListIds): Collection
    {
        $placeholders = implode(',', array_fill(0, count($checkInListIds), '?'));

        $sql = <<<SQL
            WITH valid_check_ins AS (
                SELECT attendee_id, check_in_list_id
                FROM attendee_check_ins
                WHERE deleted_at IS NULL
                AND check_in_list_id IN ($placeholders)
                GROUP BY attendee_id, check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, pcil.check_in_list_id
                     FROM attendees a
                              JOIN product_check_in_lists pcil ON a.product_id = pcil.product_id
                              JOIN orders o ON a.order_id = o.id
                              JOIN check_in_lists cil ON pcil.check_in_list_id = cil.id
                              JOIN event_settings es ON cil.event_id = es.event_id
                     WHERE a.deleted_at IS NULL
                       AND pcil.deleted_at IS NULL
                       AND pcil.check_in_list_id IN ($placeholders)
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

    public function getPerProductCheckInStatsById(int $checkInListId): Collection
    {
        $sql = <<<SQL
            WITH valid_check_ins AS (
                SELECT attendee_id, check_in_list_id
                FROM attendee_check_ins
                WHERE deleted_at IS NULL
                  AND check_in_list_id = :check_in_list_id
                GROUP BY attendee_id, check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, a.product_id, pcil.check_in_list_id
                     FROM attendees a
                              JOIN product_check_in_lists pcil ON a.product_id = pcil.product_id
                              JOIN orders o ON a.order_id = o.id
                              JOIN check_in_lists cil ON pcil.check_in_list_id = cil.id
                              JOIN event_settings es ON cil.event_id = es.event_id
                     WHERE a.deleted_at IS NULL
                       AND pcil.deleted_at IS NULL
                       AND pcil.check_in_list_id = :check_in_list_id
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
                     JOIN product_check_in_lists pcil ON pcil.product_id = p.id
                     LEFT JOIN valid_attendees va ON va.product_id = p.id
                     LEFT JOIN valid_check_ins vci ON vci.attendee_id = va.id
            WHERE pcil.check_in_list_id = :check_in_list_id
              AND pcil.deleted_at IS NULL
              AND p.deleted_at IS NULL
            GROUP BY p.id, p.title
            ORDER BY p.title;
        SQL;

        $rows = $this->db->select($sql, ['check_in_list_id' => $checkInListId]);

        return collect($rows)->map(
            static fn($row) => new CheckInListProductStatDTO(
                productId: (int)$row->product_id,
                productTitle: $row->product_title,
                totalAttendees: (int)$row->total_attendees,
                checkedInAttendees: (int)$row->checked_in_attendees,
            )
        );
    }

    public function getRecentCheckInsById(int $checkInListId, int $limit): Collection
    {
        $sql = <<<SQL
            SELECT
                a.public_id AS attendee_public_id,
                a.first_name,
                a.last_name,
                p.title AS product_title,
                aci.created_at AS checked_in_at
            FROM attendee_check_ins aci
                     JOIN attendees a ON a.id = aci.attendee_id
                     LEFT JOIN products p ON p.id = a.product_id
            WHERE aci.check_in_list_id = :check_in_list_id
              AND aci.deleted_at IS NULL
              AND a.deleted_at IS NULL
            ORDER BY aci.created_at DESC
            LIMIT :row_limit;
        SQL;

        $rows = $this->db->select($sql, [
            'check_in_list_id' => $checkInListId,
            'row_limit' => $limit,
        ]);

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
