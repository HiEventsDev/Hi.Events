<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessWalletResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\GetCashlessWalletHandler;
use Illuminate\Http\JsonResponse;

class GetCashlessWalletAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessWalletHandler $getCashlessWalletHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $eventId, int $walletId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: CashlessWalletResource::class,
            data: $this->getCashlessWalletHandler->handle($eventId, $walletId),
        );
    }
}
