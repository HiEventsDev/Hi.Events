<?php

namespace TicketKitten\Repository\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use TicketKitten\DomainObjects\PromoCodeDomainObject;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<PromoCodeDomainObject>
 */
interface PromoCodeRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}
