<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Http\Request\Cashless\CreateStaffTopupRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessTransactionResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CreateStaffTopupDTO;
use HiEvents\Services\Application\Handlers\Cashless\Public\CreateStaffTopupPublicHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class CreateStaffTopupPublicAction extends BaseCashlessSalesPointAction
{
    public function __construct(
        private readonly CreateStaffTopupPublicHandler $createStaffTopupPublicHandler,
    ) {}

    /**
     * Top up a ticket's cashless balance at a sales point
     *
     * @throws Throwable
     */
    public function __invoke(
        CreateStaffTopupRequest $request,
        string $salesPointShortId,
    ): JsonResponse {
        try {
            $transaction = $this->createStaffTopupPublicHandler->handle(new CreateStaffTopupDTO(
                sales_point_short_id: $salesPointShortId,
                attendee_public_id: $request->input('attendee_public_id'),
                amount: (float) $request->input('amount'),
                payment_method: CashlessStaffPaymentMethod::from($request->input('payment_method')),
                client_reference_id: $request->input('client_reference_id'),
                session_token: $this->getSessionToken($request),
                notes: $request->input('notes'),
            ));
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        } catch (CashlessNotEnabledException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            resource: CashlessTransactionResourcePublic::class,
            data: $transaction,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
