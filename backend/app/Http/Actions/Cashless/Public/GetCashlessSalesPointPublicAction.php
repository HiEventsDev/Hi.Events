<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessSalesPointResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\Public\GetCashlessSalesPointPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCashlessSalesPointPublicAction extends BaseCashlessSalesPointAction
{
    public function __construct(
        private readonly GetCashlessSalesPointPublicHandler $getCashlessSalesPointPublicHandler,
    ) {}

    /**
     * Get a sales point
     *
     * The product catalogue is only returned once the till session is authorised.
     */
    public function __invoke(Request $request, string $salesPointShortId): JsonResponse
    {
        try {
            $salesPoint = $this->getCashlessSalesPointPublicHandler->handle(
                salesPointShortId: $salesPointShortId,
                sessionToken: $this->getSessionToken($request),
            );
        } catch (CashlessSalesPointAccessException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
            );
        }

        return $this->resourceResponse(
            resource: CashlessSalesPointResourcePublic::class,
            data: $salesPoint,
        );
    }
}
