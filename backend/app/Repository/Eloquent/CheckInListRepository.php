<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\CheckInList;
use HiEvents\Repository\DTO\CheckedInAttendeesCountDTO;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
                GROUP BY attendee_id, check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, tcil.check_in_list_id
                     FROM attendees a
                              JOIN ticket_check_in_lists tcil ON a.ticket_id = tcil.ticket_id
                     WHERE a.deleted_at IS NULL
                       AND tcil.deleted_at IS NULL
                 )
            SELECT
                cil.id AS check_in_list_id,
                COUNT(va.id) AS total_attendees,
                COUNT(DISTINCT vci.attendee_id) AS checked_in_attendees
            FROM check_in_lists cil
                     LEFT JOIN valid_attendees va ON va.check_in_list_id = cil.id
                     LEFT JOIN valid_check_ins vci ON vci.attendee_id = va.id
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
                GROUP BY attendee_id, check_in_list_id
            ),
                 valid_attendees AS (
                     SELECT a.id, tcil.check_in_list_id
                     FROM attendees a
                              JOIN ticket_check_in_lists tcil ON a.ticket_id = tcil.ticket_id
                     WHERE a.deleted_at IS NULL
                       AND tcil.deleted_at IS NULL
                 )
            SELECT
                cil.id AS check_in_list_id,
                COUNT(va.id) AS total_attendees,
                COUNT(DISTINCT vci.attendee_id) AS checked_in_attendees
            FROM check_in_lists cil
                     LEFT JOIN valid_attendees va ON va.check_in_list_id = cil.id
                     LEFT JOIN valid_check_ins vci ON vci.attendee_id = va.id
            WHERE cil.id IN ($placeholders)
              AND cil.deleted_at IS NULL
            GROUP BY cil.id;
    SQL;

        $query = $this->db->select($sql, $checkInListIds);

        return collect($query)->map(
            static fn($item) => new CheckedInAttendeesCountDTO(
                checkInListId: $item->check_in_list_id,
                checkedInCount: $item->checked_in_attendees,
                totalAttendeesCount: $item->total_attendees,
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
            $params->sort_by ?? CheckInListDomainObject::getDefaultSort(),
            $params->sort_direction ?? CheckInListDomainObject::getDefaultSortDirection(),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }
}
