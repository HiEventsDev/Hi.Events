<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessSettingsResource;
use HiEvents\Services\Application\Handlers\Cashless\GetCashlessSettingsHandler;
use Illuminate\Http\JsonResponse;

class GetCashlessSettingsAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessSettingsHandler $getCashlessSettingsHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: CashlessSettingsResource::class,
            data: $this->getCashlessSettingsHandler->handle($eventId),
        );
    }
}
