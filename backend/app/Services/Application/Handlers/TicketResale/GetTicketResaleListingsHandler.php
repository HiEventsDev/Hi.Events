<?php

namespace HiEvents\Services\Application\Handlers\TicketResale;

use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Services\Domain\TicketResale\ResaleListingOverviewService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetTicketResaleListingsHandler
{
    public function __construct(
        private readonly ResaleListingOverviewService $overviewService,
    ) {}

    public function handle(int $eventId, QueryParamsDTO $queryParams): LengthAwarePaginator
    {
        return $this->overviewService->paginateForEvent($eventId, $queryParams);
    }
}
