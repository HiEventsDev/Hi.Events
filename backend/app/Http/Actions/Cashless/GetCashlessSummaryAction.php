<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessSummaryResource;
use HiEvents\Services\Application\Handlers\Cashless\GetCashlessSummaryHandler;
use Illuminate\Http\JsonResponse;

class GetCashlessSummaryAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessSummaryHandler $getCashlessSummaryHandler,
    ) {}

    /**
     * Global cashless figures for an event
     */
    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: CashlessSummaryResource::class,
            data: $this->getCashlessSummaryHandler->handle($eventId),
        );
    }
}
