<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless;

use HiEvents\Services\Domain\Cashless\CashlessStatsFetchService;
use Illuminate\Support\Collection;

class GetCashlessStatsHandler
{
    public function __construct(
        private readonly CashlessStatsFetchService $cashlessStatsFetchService,
    ) {}

    public function handle(int $eventId, string $startDate, string $endDate): Collection
    {
        return $this->cashlessStatsFetchService->getDailyStats($eventId, $startDate, $endDate);
    }
}
