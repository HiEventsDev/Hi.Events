<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<EventDomainObject>
 */
interface EventRepositoryInterface extends RepositoryInterface
{
    public function findEvents(array $where, QueryParamsDTO $params): LengthAwarePaginator;
}
