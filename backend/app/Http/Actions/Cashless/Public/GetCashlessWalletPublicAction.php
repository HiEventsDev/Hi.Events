<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Public;

use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Cashless\CashlessWalletResourcePublic;
use HiEvents\Services\Application\Handlers\Cashless\Public\GetCashlessWalletPublicHandler;
use Illuminate\Http\JsonResponse;

class GetCashlessWalletPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessWalletPublicHandler $getCashlessWalletPublicHandler,
    ) {}

    /**
     * Get the cashless balance attached to a ticket
     */
    public function __invoke(int $eventId, string $ticketReference): JsonResponse
    {
        try {
            $wallet = $this->getCashlessWalletPublicHandler->handle($eventId, $ticketReference);
        } catch (CashlessNotEnabledException|CashlessWalletUnavailableException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
            );
        }

        return $this->resourceResponse(
            resource: CashlessWalletResourcePublic::class,
            data: $wallet,
        );
    }
}
