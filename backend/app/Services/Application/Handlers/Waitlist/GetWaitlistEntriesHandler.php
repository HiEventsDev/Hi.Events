<?php

namespace HiEvents\Services\Application\Handlers\Waitlist;

use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetWaitlistEntriesHandler
{
    public function __construct(
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
    )
    {
    }

    public function handle(int $eventId, QueryParamsDTO $queryParams): LengthAwarePaginator
    {
        return $this->waitlistEntryRepository->findByEventId($eventId, $queryParams);
    }
}
