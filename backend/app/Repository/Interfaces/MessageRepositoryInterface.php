<?php

namespace HiEvents\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\Http\DataTransferObjects\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<MessageDomainObject>
 */
interface MessageRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}
