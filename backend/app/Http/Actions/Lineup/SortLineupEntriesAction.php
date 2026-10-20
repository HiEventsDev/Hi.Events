<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\SortLineupEntriesRequest;
use HiEvents\Services\Application\Handlers\Lineup\SortLineupEntriesHandler;
use Illuminate\Http\Response;

class SortLineupEntriesAction extends BaseAction
{
    public function __construct(
        private readonly SortLineupEntriesHandler $handler,
    ) {}

    public function __invoke(int $eventId, SortLineupEntriesRequest $request): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle($eventId, array_map('intval', $request->validated('sorted_ids')));

        return $this->noContentResponse();
    }
}
