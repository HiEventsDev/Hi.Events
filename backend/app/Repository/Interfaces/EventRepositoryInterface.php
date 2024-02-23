<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<EventDomainObject>
 */
interface EventRepositoryInterface extends RepositoryInterface
{
    public function getAvailableTicketQuantities(int $eventId): Collection;

    public function findEvents(array $where, QueryParamsDTO $params): LengthAwarePaginator;
}
