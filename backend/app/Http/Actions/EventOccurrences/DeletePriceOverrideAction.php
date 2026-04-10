<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DeletePriceOverrideHandler;
use Illuminate\Http\Response;

class DeletePriceOverrideAction extends BaseAction
{
    public function __construct(
        private readonly DeletePriceOverrideHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId, int $overrideId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $occurrenceId, $overrideId);

        return $this->deletedResponse();
    }
}
