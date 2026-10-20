<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Services\Application\Handlers\DeleteSeatMapHandler;
use Illuminate\Http\Response;

class DeleteSeatMapAction extends BaseSeatMapAction
{
    public function __construct(
        private readonly DeleteSeatMapHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $seatMapId): Response
    {
        $this->authorizeSeatMapAccess($organizerId);

        $this->handler->handle($seatMapId, $organizerId, $this->getAuthenticatedAccountId());

        return $this->deletedResponse();
    }
}
