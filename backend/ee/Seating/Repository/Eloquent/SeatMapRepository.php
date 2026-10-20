<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Enterprise\Licensing\Repository\UsageScope;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\SeatMap;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<SeatMapDomainObject>
 */
class SeatMapRepository extends BaseRepository implements SeatMapRepositoryInterface
{
    private const LIST_COLUMNS = [
        SeatMapDomainObjectAbstract::ID,
        SeatMapDomainObjectAbstract::ACCOUNT_ID,
        SeatMapDomainObjectAbstract::ORGANIZER_ID,
        SeatMapDomainObjectAbstract::NAME,
        SeatMapDomainObjectAbstract::VERSION,
        SeatMapDomainObjectAbstract::SEAT_COUNT,
        SeatMapDomainObjectAbstract::LAYOUT,
        SeatMapDomainObjectAbstract::CREATED_AT,
        SeatMapDomainObjectAbstract::UPDATED_AT,
    ];

    protected function getModel(): string
    {
        return SeatMap::class;
    }

    public function getDomainObject(): string
    {
        return SeatMapDomainObject::class;
    }

    public function findSummariesByOrganizerId(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $this->model = $this->model->newQuery()->orderBy(
            column: $this->validateSortColumn($params->sort_by, SeatMapDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, SeatMapDomainObject::class),
        );

        if (! empty($params->query)) {
            $this->model = $this->model->whereRaw(
                'LOWER('.SeatMapDomainObjectAbstract::NAME.') LIKE ?',
                ['%'.strtolower($params->query).'%'],
            );
        }

        return $this->paginateWhere(
            where: [
                SeatMapDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                SeatMapDomainObjectAbstract::ACCOUNT_ID => $accountId,
            ],
            limit: $params->per_page,
            columns: self::LIST_COLUMNS,
            page: $params->page,
        );
    }

    public function existsForLiveOrganizer(?int $accountId): bool
    {
        return $this->runQuery(fn () => UsageScope::liveOrganizer(
            DB::table('seat_maps')
                ->join('organizers', 'organizers.id', '=', 'seat_maps.organizer_id')
                ->whereNull('seat_maps.deleted_at'),
            $accountId,
        )->exists());
    }
}
