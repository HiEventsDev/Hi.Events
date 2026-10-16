<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Widget;

use HiEvents\DomainObjects\Generated\WidgetDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;

class DeleteWidgetHandler
{
    public function __construct(
        private readonly WidgetRepositoryInterface $widgetRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $widgetId, int $organizerId, ?int $eventId): void
    {
        $widget = $this->widgetRepository->findFirstWhere([
            WidgetDomainObjectAbstract::ID => $widgetId,
            WidgetDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            WidgetDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($widget === null) {
            throw new ResourceNotFoundException(__('Widget not found'));
        }

        $this->widgetRepository->deleteById($widgetId);
    }
}
