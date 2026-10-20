<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\WidgetDomainObject;
use HiEvents\Repository\DTO\WidgetStatsDTO;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<WidgetDomainObject>
 */
interface WidgetRepositoryInterface extends RepositoryInterface
{
    public function findForOrderAttribution(string $shortId, int $eventId, int $organizerId): ?WidgetDomainObject;

    /**
     * @param  int[]  $widgetIds
     * @return Collection<int, WidgetStatsDTO>
     */
    public function getStatsForWidgets(array $widgetIds): Collection;
}
