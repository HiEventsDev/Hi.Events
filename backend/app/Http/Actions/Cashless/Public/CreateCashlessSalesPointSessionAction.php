<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Cashless\CreateCashlessSalesPointSessionRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use Illuminate\Http\JsonResponse;

class CreateCashlessSalesPointSessionAction extends BaseAction
{
    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
    ) {}

    /**
     * Open a till session
     *
     * Exchanges the sales point PIN for a short-lived session token, which must be sent as the
     * `X-Cashless-Session` header on every other sales point request.
     */
    public function __invoke(
        CreateCashlessSalesPointSessionRequest $request,
        string $salesPointShortId,
    ): JsonResponse {
        try {
            $token = $this->salesPointAccessService->authenticate(
                shortId: $salesPointShortId,
                pin: $request->input('pin'),
            );
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        return $this->jsonResponse(['token' => $token], ResponseCodes::HTTP_CREATED);
    }
}
