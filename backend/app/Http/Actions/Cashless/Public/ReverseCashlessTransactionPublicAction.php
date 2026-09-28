<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessTransactionResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\Public\ReverseCashlessTransactionPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ReverseCashlessTransactionPublicAction extends BaseCashlessSalesPointAction
{
    public function __construct(
        private readonly ReverseCashlessTransactionPublicHandler $reverseCashlessTransactionPublicHandler,
    ) {}

    /**
     * Reverse a transaction made at this sales point
     *
     * @throws Throwable
     */
    public function __invoke(
        Request $request,
        string $salesPointShortId,
        string $transactionShortId,
    ): JsonResponse {
        try {
            $reversal = $this->reverseCashlessTransactionPublicHandler->handle(
                salesPointShortId: $salesPointShortId,
                transactionShortId: $transactionShortId,
                sessionToken: $this->getSessionToken($request),
            );
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        } catch (CashlessTransactionNotReversibleException|InsufficientCashlessBalanceException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        } catch (CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            resource: CashlessTransactionResourcePublic::class,
            data: $reversal,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
