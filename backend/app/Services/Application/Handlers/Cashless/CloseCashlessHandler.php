<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless;

use HiEvents\Exceptions\CashlessClosureNotAllowedException;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\Cashless\CashlessClosureService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessClosureResultDTO;
use Throwable;

class CloseCashlessHandler
{
    public function __construct(
        private readonly CashlessClosureService $cashlessClosureService,
    ) {}

    /**
     * @throws CashlessClosureNotAllowedException
     * @throws CashlessNotEnabledException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(int $eventId, int $userId): CashlessClosureResultDTO
    {
        return $this->cashlessClosureService->close($eventId, $userId);
    }
}
