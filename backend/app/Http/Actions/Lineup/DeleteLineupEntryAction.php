<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Lineup\DeleteLineupEntryHandler;
use Illuminate\Http\Response;

class DeleteLineupEntryAction extends BaseAction
{
    public function __construct(
        private readonly DeleteLineupEntryHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $lineupEntryId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $lineupEntryId);

        return $this->deletedResponse();
    }
}
