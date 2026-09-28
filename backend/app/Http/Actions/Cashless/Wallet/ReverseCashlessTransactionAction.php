<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\ReverseCashlessTransactionRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessTransactionResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\ReverseCashlessTransactionHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class ReverseCashlessTransactionAction extends BaseAction
{
    public function __construct(
        private readonly ReverseCashlessTransactionHandler $reverseCashlessTransactionHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function __invoke(
        ReverseCashlessTransactionRequest $request,
        int $eventId,
        int $transactionId,
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $reversal = $this->reverseCashlessTransactionHandler->handle(
                eventId: $eventId,
                transactionId: $transactionId,
                userId: $this->getAuthenticatedUser()->getId(),
                notes: $request->input('notes'),
            );
        } catch (CashlessTransactionNotReversibleException|InsufficientCashlessBalanceException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: CashlessTransactionResource::class,
            data: $reversal,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
