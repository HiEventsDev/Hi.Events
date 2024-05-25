<?php

namespace HiEvents\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;
use HiEvents\Repository\Interfaces\RepositoryInterface;

/**
 * @extends BaseRepository<AttendeeDomainObject>
 */
interface AttendeeRepositoryInterface extends RepositoryInterFace
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByEventIdForExport(int $eventId): Collection;
}
