<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exports\Lineup\LineupExport;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\GetEventLineupHandler;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportEventLineupAction extends BaseAction
{
    public function __construct(
        private readonly GetEventLineupHandler $handler,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly LineupExport $export,
    ) {}

    public function __invoke(int $eventId): BinaryFileResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository->findById($eventId);

        return Excel::download(
            $this->export->withData(
                $this->handler->handle($eventId, forPublic: false),
                $event->getTimezone() ?? 'UTC',
            ),
            Str::slug($event->getTitle()).'-run-sheet.xlsx',
        );
    }
}
