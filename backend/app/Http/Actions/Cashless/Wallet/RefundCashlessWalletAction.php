<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\Enums\CashlessRefundMethod;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\RefundCashlessWalletRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessRefundResultResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\RefundCashlessWalletHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class RefundCashlessWalletAction extends BaseAction
{
    public function __construct(
        private readonly RefundCashlessWalletHandler $refundCashlessWalletHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function __invoke(RefundCashlessWalletRequest $request, int $eventId, int $walletId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $result = $this->refundCashlessWalletHandler->handle(
                eventId: $eventId,
                walletId: $walletId,
                method: CashlessRefundMethod::from($request->input('method')),
                userId: $this->getAuthenticatedUser()->getId(),
            );
        } catch (RefundNotPossibleException|CashlessNotEnabledException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: CashlessRefundResultResource::class,
            data: $result,
        );
    }
}
