<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Services\Domain\Cashless\CashlessQuoteService;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessPurchaseItemRequestDTO;
use HiEvents\Services\Domain\Cashless\DTO\CashlessQuoteDTO;
use Illuminate\Support\Collection;

class GetCashlessQuotePublicHandler
{
    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
        private readonly CashlessQuoteService $quoteService,
    ) {}

    /**
     * @param  Collection<CashlessPurchaseItemRequestDTO>  $items
     *
     * @throws CashlessSalesPointAccessException
     * @throws CashlessNotEnabledException
     */
    public function handle(
        string $salesPointShortId,
        ?string $sessionToken,
        Collection $items,
        ?float $topupAmount,
    ): CashlessQuoteDTO {
        $salesPoint = $this->salesPointAccessService->resolveAuthorised($salesPointShortId, $sessionToken);

        if ($topupAmount !== null) {
            return $this->quoteService->quoteTopup($salesPoint->getEventId(), $topupAmount);
        }

        return $this->quoteService->quoteBasket($salesPoint, $items);
    }
}
