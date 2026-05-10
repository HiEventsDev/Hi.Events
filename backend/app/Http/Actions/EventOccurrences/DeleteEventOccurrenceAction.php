<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\EventOccurrence\DeleteEventOccurrenceHandler;
use Illuminate\Http\Response;

class DeleteEventOccurrenceAction extends BaseAction
{
    public function __construct(
        private readonly DeleteEventOccurrenceHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $occurrenceId);

        return $this->deletedResponse();
    }
}
