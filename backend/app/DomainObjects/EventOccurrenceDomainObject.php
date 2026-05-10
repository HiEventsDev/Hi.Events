<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use Illuminate\Support\Collection;

class EventOccurrenceDomainObject extends EventOccurrenceDomainObjectAbstract implements IsSortable, IsFilterable
{
    private ?EventDomainObject $event = null;

    private ?Collection $orderItems = null;

    private ?Collection $attendees = null;

    private ?Collection $checkInLists = null;

    private ?Collection $priceOverrides = null;

    private ?EventOccurrenceStatisticDomainObject $eventOccurrenceStatistics = null;

    public static function getAllowedFilterFields(): array
    {
        return [
            self::STATUS,
            self::START_DATE,
        ];
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::START_DATE => [
                    'asc' => __('Earliest first'),
                    'desc' => __('Latest first'),
                ],
            ]
        );
    }

    public static function getDefaultSort(): string
    {
        return self::START_DATE;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public function setEvent(?EventDomainObject $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function getEvent(): ?EventDomainObject
    {
        return $this->event;
    }

    public function setOrderItems(?Collection $orderItems): self
    {
        $this->orderItems = $orderItems;
        return $this;
    }

    public function getOrderItems(): ?Collection
    {
        return $this->orderItems;
    }

    public function setAttendees(?Collection $attendees): self
    {
        $this->attendees = $attendees;
        return $this;
    }

    public function getAttendees(): ?Collection
    {
        return $this->attendees;
    }

    public function setCheckInLists(?Collection $checkInLists): self
    {
        $this->checkInLists = $checkInLists;
        return $this;
    }

    public function getCheckInLists(): ?Collection
    {
        return $this->checkInLists;
    }

    public function setPriceOverrides(?Collection $priceOverrides): self
    {
        $this->priceOverrides = $priceOverrides;
        return $this;
    }

    public function getPriceOverrides(): ?Collection
    {
        return $this->priceOverrides;
    }

    public function setEventOccurrenceStatistics(?EventOccurrenceStatisticDomainObject $statistics): self
    {
        $this->eventOccurrenceStatistics = $statistics;
        return $this;
    }

    public function getEventOccurrenceStatistics(): ?EventOccurrenceStatisticDomainObject
    {
        return $this->eventOccurrenceStatistics;
    }

    public function isActive(): bool
    {
        return $this->getStatus() === EventOccurrenceStatus::ACTIVE->name;
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === EventOccurrenceStatus::CANCELLED->name;
    }

    public function isSoldOut(): bool
    {
        return $this->getStatus() === EventOccurrenceStatus::SOLD_OUT->name;
    }

    public function isPast(): bool
    {
        $endDate = $this->getEndDate() ?? $this->getStartDate();
        return Carbon::parse($endDate, 'UTC')->isPast();
    }

    public function isFuture(): bool
    {
        return Carbon::parse($this->getStartDate(), 'UTC')->isFuture();
    }

    public function getAvailableCapacity(): ?int
    {
        if ($this->getCapacity() === null) {
            return null;
        }

        return max(0, $this->getCapacity() - $this->getUsedCapacity());
    }
}
