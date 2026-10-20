<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Resources\Widget\WidgetResource;
use HiEvents\Services\Application\Handlers\Widget\GetWidgetsHandler;
use Illuminate\Http\JsonResponse;

class GetEventWidgetsAction extends BaseAction
{
    public function __construct(
        private readonly GetWidgetsHandler $handler,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository->findById($eventId);

        return $this->resourceResponse(
            resource: WidgetResource::class,
            data: $this->handler->handle($event->getOrganizerId(), $eventId),
        );
    }
}
