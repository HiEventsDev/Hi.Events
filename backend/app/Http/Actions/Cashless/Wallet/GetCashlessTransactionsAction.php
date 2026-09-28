<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\Wallet;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessTransactionResource;
use HiEvents\Services\Application\Handlers\Cashless\Wallet\GetCashlessTransactionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCashlessTransactionsAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessTransactionsHandler $getCashlessTransactionsHandler,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->filterableResourceResponse(
            resource: CashlessTransactionResource::class,
            data: $this->getCashlessTransactionsHandler->handle($eventId, $this->getPaginationQueryParams($request)),
            domainObject: CashlessTransactionDomainObject::class,
        );
    }
}
