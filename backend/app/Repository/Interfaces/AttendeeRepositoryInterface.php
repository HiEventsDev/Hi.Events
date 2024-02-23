<?php

namespace TicketKitten\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<AttendeeDomainObject>
 */
interface AttendeeRepositoryInterface extends RepositoryInterFace
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}
