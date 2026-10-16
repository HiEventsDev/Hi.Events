<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Widget\DeleteWidgetHandler;
use Illuminate\Http\Response;

class DeleteEventWidgetAction extends BaseAction
{
    public function __construct(
        private readonly DeleteWidgetHandler $handler,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $eventId, int $widgetId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository->findById($eventId);

        $this->handler->handle($widgetId, $event->getOrganizerId(), $eventId);

        return $this->deletedResponse();
    }
}
