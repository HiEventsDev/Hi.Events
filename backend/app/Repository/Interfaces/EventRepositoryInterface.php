<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<EventDomainObject>
 */
interface EventRepositoryInterface extends RepositoryInterface
{
    public function findEventsForOrganizer(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findEvents(array $where, QueryParamsDTO $params): LengthAwarePaginator;
}
