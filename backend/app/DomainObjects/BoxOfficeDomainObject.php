<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class BoxOfficeDomainObject extends Generated\BoxOfficeDomainObjectAbstract implements IsSortable
{
    private ?Collection $products = null;

    private ?EventDomainObject $event = null;

    private ?EventOccurrenceDomainObject $eventOccurrence = null;

    private ?CheckInListDomainObject $checkInList = null;

    private int $salesCount = 0;

    private float $grossSales = 0.0;

    public static function getDefaultSort(): string
    {
        return static::CREATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::NAME => [
                    'asc' => __('Name A-Z'),
                    'desc' => __('Name Z-A'),
                ],
                self::CREATED_AT => [
                    'asc' => __('Oldest first'),
                    'desc' => __('Newest first'),
                ],
                self::UPDATED_AT => [
                    'asc' => __('Updated oldest first'),
                    'desc' => __('Updated newest first'),
                ],
            ]
        );
    }

    public function getProducts(): ?Collection
    {
        return $this->products;
    }

    public function setProducts(?Collection $products): static
    {
        $this->products = $products;

        return $this;
    }

    public function getEvent(): ?EventDomainObject
    {
        return $this->event;
    }

    public function setEvent(?EventDomainObject $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getEventOccurrence(): ?EventOccurrenceDomainObject
    {
        return $this->eventOccurrence;
    }

    public function setEventOccurrence(?EventOccurrenceDomainObject $eventOccurrence): static
    {
        $this->eventOccurrence = $eventOccurrence;

        return $this;
    }

    public function getCheckInList(): ?CheckInListDomainObject
    {
        return $this->checkInList;
    }

    public function setCheckInList(?CheckInListDomainObject $checkInList): static
    {
        $this->checkInList = $checkInList;

        return $this;
    }

    public function getSalesCount(): int
    {
        return $this->salesCount;
    }

    public function setSalesCount(int $salesCount): static
    {
        $this->salesCount = $salesCount;

        return $this;
    }

    public function getGrossSales(): float
    {
        return $this->grossSales;
    }

    public function setGrossSales(float $grossSales): static
    {
        $this->grossSales = $grossSales;

        return $this;
    }

    public function hasPin(): bool
    {
        return $this->getPinHash() !== null;
    }

    public function isExpired(): bool
    {
        if ($this->getExpiresAt() === null) {
            return false;
        }

        return Carbon::parse($this->getExpiresAt())->isPast();
    }

    public function isActivated(): bool
    {
        if ($this->getActivatesAt() === null) {
            return true;
        }

        return Carbon::parse($this->getActivatesAt())->isPast();
    }
}
