<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessTransactionResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\Public\GetCashlessSalesPointTransactionsPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCashlessSalesPointTransactionsPublicAction extends BaseCashlessSalesPointAction
{
    public function __construct(
        private readonly GetCashlessSalesPointTransactionsPublicHandler $getCashlessSalesPointTransactionsPublicHandler,
    ) {}

    /**
     * List the transactions made at a sales point
     */
    public function __invoke(Request $request, string $salesPointShortId): JsonResponse
    {
        try {
            $transactions = $this->getCashlessSalesPointTransactionsPublicHandler->handle(
                salesPointShortId: $salesPointShortId,
                sessionToken: $this->getSessionToken($request),
            );
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        }

        return $this->resourceResponse(
            resource: CashlessTransactionResourcePublic::class,
            data: $transactions,
        );
    }
}
