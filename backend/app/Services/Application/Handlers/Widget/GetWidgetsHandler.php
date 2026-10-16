<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Widget;

use HiEvents\DomainObjects\Generated\WidgetDomainObjectAbstract;
use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;
use HiEvents\Services\Domain\Widget\WidgetStatsService;
use Illuminate\Support\Collection;

class GetWidgetsHandler
{
    public function __construct(
        private readonly WidgetRepositoryInterface $widgetRepository,
        private readonly WidgetStatsService $widgetStatsService,
    ) {}

    /**
     * @return Collection<int, WidgetDomainObject>
     */
    public function handle(int $organizerId, ?int $eventId): Collection
    {
        return $this->widgetStatsService->attachStats($this->widgetRepository->findWhere(
            where: [
                WidgetDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                WidgetDomainObjectAbstract::EVENT_ID => $eventId,
            ],
            orderAndDirections: [
                new OrderAndDirection(WidgetDomainObjectAbstract::ID, 'desc'),
            ],
        ));
    }
}
