<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class EventSeatMapDomainObject extends Generated\EventSeatMapDomainObjectAbstract
{
    private ?SeatMapDomainObject $seatMap = null;

    private ?Collection $eventSeatMapBandProducts = null;

    public function getSeatMap(): ?SeatMapDomainObject
    {
        return $this->seatMap;
    }

    public function setSeatMap(?SeatMapDomainObject $seatMap): self
    {
        $this->seatMap = $seatMap;

        return $this;
    }

    /**
     * @return Collection<EventSeatMapBandProductDomainObject>|null
     */
    public function getEventSeatMapBandProducts(): ?Collection
    {
        return $this->eventSeatMapBandProducts;
    }

    public function setEventSeatMapBandProducts(?Collection $eventSeatMapBandProducts): self
    {
        $this->eventSeatMapBandProducts = $eventSeatMapBandProducts;

        return $this;
    }

    public function isUpdateAvailableFromSource(): bool
    {
        return $this->seatMap !== null && $this->seatMap->getVersion() > (int) $this->getSourceVersion();
    }
}
