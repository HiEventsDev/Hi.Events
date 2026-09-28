<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\SalesPoint;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Cashless\SalesPoint\DeleteCashlessSalesPointHandler;
use Illuminate\Http\Response;

class DeleteCashlessSalesPointAction extends BaseAction
{
    public function __construct(
        private readonly DeleteCashlessSalesPointHandler $deleteCashlessSalesPointHandler,
    ) {}

    public function __invoke(int $eventId, int $salesPointId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->deleteCashlessSalesPointHandler->handle($eventId, $salesPointId);

        return $this->deletedResponse();
    }
}
