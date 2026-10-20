<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Lineup\DeleteScheduleItemHandler;
use Illuminate\Http\Response;

class DeleteScheduleItemAction extends BaseAction
{
    public function __construct(
        private readonly DeleteScheduleItemHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $scheduleItemId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $scheduleItemId);

        return $this->deletedResponse();
    }
}
