<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessWalletResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\GetCashlessWalletsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCashlessWalletsAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessWalletsHandler $getCashlessWalletsHandler,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->filterableResourceResponse(
            resource: CashlessWalletResource::class,
            data: $this->getCashlessWalletsHandler->handle($eventId, $this->getPaginationQueryParams($request)),
            domainObject: CashlessWalletDomainObject::class,
        );
    }
}
