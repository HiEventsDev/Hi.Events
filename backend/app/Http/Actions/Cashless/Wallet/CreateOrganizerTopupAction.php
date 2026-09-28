<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\CreateOrganizerTopupRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessTransactionResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\CreateOrganizerTopupHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class CreateOrganizerTopupAction extends BaseAction
{
    public function __construct(
        private readonly CreateOrganizerTopupHandler $createOrganizerTopupHandler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(CreateOrganizerTopupRequest $request, int $eventId, int $walletId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $transaction = $this->createOrganizerTopupHandler->handle(
                eventId: $eventId,
                walletId: $walletId,
                amount: (float) $request->input('amount'),
                paymentMethod: CashlessStaffPaymentMethod::from($request->input('payment_method')),
                userId: $this->getAuthenticatedUser()->getId(),
                notes: $request->input('notes'),
            );
        } catch (CashlessNotEnabledException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: CashlessTransactionResource::class,
            data: $transaction,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
