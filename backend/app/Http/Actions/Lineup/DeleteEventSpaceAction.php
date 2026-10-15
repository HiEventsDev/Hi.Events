<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Lineup\DeleteEventSpaceHandler;
use Illuminate\Http\Response;

class DeleteEventSpaceAction extends BaseAction
{
    public function __construct(
        private readonly DeleteEventSpaceHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $spaceId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, $spaceId);

        return $this->deletedResponse();
    }
}
