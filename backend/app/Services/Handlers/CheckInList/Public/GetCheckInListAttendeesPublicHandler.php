<?php

namespace HiEvents\Services\Handlers\CheckInList\Public;

use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;

class GetCheckInListAttendeesPublicHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    )
    {
    }

    public function handle(string $shortId, QueryParamsDTO $queryParams): Paginator
    {
        return $this->attendeeRepository->getAttendeesByCheckInShortId($shortId, $queryParams);
    }
}
