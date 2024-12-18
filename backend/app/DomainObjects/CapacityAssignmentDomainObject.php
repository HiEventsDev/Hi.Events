<?php

namespace HiEvents\DomainObjects;

use HiEvents\Constants;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class CapacityAssignmentDomainObject extends Generated\CapacityAssignmentDomainObjectAbstract implements IsSortable
{
    public ?Collection $products = null;

    public static function getDefaultSort(): string
    {
        return static::CREATED_AT;
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
                    'asc' => __('Oldest first'),
                    'desc' => __('Newest first'),
                ],
                self::UPDATED_AT => [
                    'asc' => __('Updated oldest first'),
                    'desc' => __('Updated newest first'),
                ],
                self::USED_CAPACITY => [
                    'desc' => __('Most capacity used'),
                    'asc' => __('Least capacity used'),
                ],
                self::CAPACITY => [
                    'desc' => __('Least capacity'),
                    'asc' => __('Most capacity'),
                ],
            ]
        );
    }

    public function getPercentageUsed(): float
    {
        if (!$this->getCapacity()) {
            return 0;
        }

        return round(($this->getUsedCapacity() / $this->getCapacity()) * 100, 2);
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

    public function isCapacityUnlimited(): bool
    {
        return is_null($this->getCapacity());
    }

    public function getAvailableCapacity(): int
    {
        if ($this->isCapacityUnlimited()) {
            return Constants::INFINITE;
        }

        return $this->getCapacity() - $this->getUsedCapacity();
    }
}
