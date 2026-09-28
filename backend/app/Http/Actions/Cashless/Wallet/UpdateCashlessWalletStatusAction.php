<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\UpdateCashlessWalletStatusRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessWalletResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\UpdateCashlessWalletStatusHandler;
use Illuminate\Http\JsonResponse;

class UpdateCashlessWalletStatusAction extends BaseAction
{
    public function __construct(
        private readonly UpdateCashlessWalletStatusHandler $updateCashlessWalletStatusHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(
        UpdateCashlessWalletStatusRequest $request,
        int $eventId,
        int $walletId,
    ): JsonResponse {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $wallet = $this->updateCashlessWalletStatusHandler->handle(
                eventId: $eventId,
                walletId: $walletId,
                status: CashlessWalletStatus::from($request->input('status')),
            );
        } catch (CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: CashlessWalletResource::class,
            data: $wallet,
        );
    }
}
