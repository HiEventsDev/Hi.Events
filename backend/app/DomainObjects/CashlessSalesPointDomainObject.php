<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class CashlessSalesPointDomainObject extends Generated\CashlessSalesPointDomainObjectAbstract implements IsSortable
{
    /** @var Collection<ProductDomainObject>|null */
    private ?Collection $products = null;

    private ?EventDomainObject $event = null;

    private ?float $salesTotal = null;

    private ?int $transactionCount = null;

    public static function getDefaultSort(): string
    {
        return self::CREATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
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
                    'desc' => __('Newest first'),
                    'asc' => __('Oldest first'),
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

    public function getSalesTotal(): ?float
    {
        return $this->salesTotal;
    }

    public function setSalesTotal(?float $salesTotal): static
    {
        $this->salesTotal = $salesTotal ?? 0.0;

        return $this;
    }

    public function getTransactionCount(): ?int
    {
        return $this->transactionCount;
    }

    public function setTransactionCount(?int $transactionCount): static
    {
        $this->transactionCount = $transactionCount ?? 0;

        return $this;
    }

    public function isExpired(string $timezone): bool
    {
        if ($this->getExpiresAt() === null) {
            return false;
        }

        return Carbon::parse($this->getExpiresAt())->setTimezone($timezone)->isPast();
    }

    public function isActivated(string $timezone): bool
    {
        if ($this->getActivatesAt() === null) {
            return true;
        }

        return Carbon::parse($this->getActivatesAt())->setTimezone($timezone)->isPast();
    }
}
