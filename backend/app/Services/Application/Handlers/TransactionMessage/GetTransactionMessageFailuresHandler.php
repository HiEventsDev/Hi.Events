<?php

namespace HiEvents\Services\Application\Handlers\TransactionMessage;

use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetTransactionMessageFailuresHandler
{
    public function __construct(
        private readonly OutgoingTransactionMessageRepositoryInterface $repository,
    )
    {
    }

    public function handle(int $eventId, QueryParamsDTO $params, bool $showResolved = false): LengthAwarePaginator
    {
        return $this->repository->getFailuresForEvent($eventId, $params->per_page, $showResolved);
    }
}
