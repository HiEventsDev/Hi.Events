<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSeatMapsHandler
{
    public function __construct(
        private readonly SeatMapRepositoryInterface $seatMapRepository,
    ) {}

    public function handle(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->seatMapRepository->findSummariesByOrganizerId($organizerId, $accountId, $params);
    }
}
