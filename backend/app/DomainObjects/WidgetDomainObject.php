<?php

namespace HiEvents\DomainObjects;

use HiEvents\Repository\DTO\WidgetStatsDTO;

class WidgetDomainObject extends Generated\WidgetDomainObjectAbstract
{
    private ?WidgetStatsDTO $stats = null;

    public function getStats(): WidgetStatsDTO
    {
        return $this->stats ?? new WidgetStatsDTO;
    }

    public function setStats(WidgetStatsDTO $stats): self
    {
        $this->stats = $stats;

        return $this;
    }
}
