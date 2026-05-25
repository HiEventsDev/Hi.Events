<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Location;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<LocationDomainObject>
 */
class LocationRepository extends BaseRepository implements LocationRepositoryInterface
{
    protected function getModel(): string
    {
        return Location::class;
    }

    public function getDomainObject(): string
    {
        return LocationDomainObject::class;
    }

    public function findByOrganizerId(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $this->model = $this->model->newQuery()->orderBy(
            column: $this->validateSortColumn($params->sort_by, LocationDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, LocationDomainObject::class),
        );

        if (! empty($params->filter_fields)) {
            $this->applyFilterFields($params, LocationDomainObject::getAllowedFilterFields());
        }

        if (! empty($params->query)) {
            $needle = '%'.strtolower($params->query).'%';
            $this->model = $this->model
                ->where(function ($query) use ($needle) {
                    $query
                        ->whereRaw('LOWER('.LocationDomainObjectAbstract::NAME.') LIKE ?', [$needle])
                        ->orWhereRaw("LOWER(structured_address->>'venue_name') LIKE ?", [$needle])
                        ->orWhereRaw("LOWER(structured_address->>'address_line_1') LIKE ?", [$needle])
                        ->orWhereRaw("LOWER(structured_address->>'city') LIKE ?", [$needle]);
                });
        }

        return $this->paginateWhere(
            where: [
                LocationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                LocationDomainObjectAbstract::ACCOUNT_ID => $accountId,
            ],
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function isReferenced(int $locationId): bool
    {
        $eventLocationCount = DB::table('event_locations')
            ->where('location_id', $locationId)
            ->whereNull('deleted_at')
            ->count();

        if ($eventLocationCount > 0) {
            return true;
        }

        $organizerCount = DB::table('organizers')
            ->where('location_id', $locationId)
            ->whereNull('deleted_at')
            ->count();

        return $organizerCount > 0;
    }
}
