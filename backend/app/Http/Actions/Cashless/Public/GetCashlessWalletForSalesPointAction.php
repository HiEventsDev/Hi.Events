<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessWalletResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\Public\GetCashlessWalletForSalesPointHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCashlessWalletForSalesPointAction extends BaseCashlessSalesPointAction
{
    public function __construct(
        private readonly GetCashlessWalletForSalesPointHandler $getCashlessWalletForSalesPointHandler,
    ) {}

    /**
     * Look up a ticket's cashless balance from a sales point
     *
     * The identifier is the attendee public id encoded in the ticket QR code.
     */
    public function __invoke(
        Request $request,
        string $salesPointShortId,
        string $attendeePublicId,
    ): JsonResponse {
        try {
            $wallet = $this->getCashlessWalletForSalesPointHandler->handle(
                salesPointShortId: $salesPointShortId,
                attendeePublicId: $attendeePublicId,
                sessionToken: $this->getSessionToken($request),
            );
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        } catch (CashlessWalletUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(
            resource: CashlessWalletResourcePublic::class,
            data: $wallet,
        );
    }
}
