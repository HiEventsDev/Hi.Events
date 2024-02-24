<?php

namespace HiEvents\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Http\DataTransferObjects\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<AttendeeDomainObject>
 */
interface AttendeeRepositoryInterface extends RepositoryInterFace
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}
