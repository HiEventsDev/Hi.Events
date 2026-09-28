<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless;

use HiEvents\Services\Domain\Cashless\CashlessSummaryService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessSummaryDTO;

class GetCashlessSummaryHandler
{
    public function __construct(
        private readonly CashlessSummaryService $cashlessSummaryService,
    ) {}

    public function handle(int $eventId): CashlessSummaryDTO
    {
        return $this->cashlessSummaryService->getSummary($eventId);
    }
}
