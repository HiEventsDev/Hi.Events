<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\WaitlistEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;

class WaitlistEntryDomainObject extends WaitlistEntryDomainObjectAbstract implements IsFilterable, IsSortable
{
    public ?OrderDomainObject $order = null;

    public ?ProductPriceDomainObject $productPrice = null;

    public ?EventOccurrenceDomainObject $eventOccurrence = null;

    public static function getDefaultSort(): string
    {
        return static::POSITION;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAllowedFilterFields(): array
    {
        return [
            self::STATUS,
            self::PRODUCT_PRICE_ID,
            self::EMAIL,
            self::EVENT_OCCURRENCE_ID,
        ];
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::POSITION => [
                    'asc' => __('Position ascending'),
                    'desc' => __('Position descending'),
                ],
                self::CREATED_AT => [
                    'asc' => __('Oldest first'),
                    'desc' => __('Newest first'),
                ],
                self::STATUS => [
                    'asc' => __('Status A-Z'),
                    'desc' => __('Status Z-A'),
                ],
            ]
        );
    }

    public function setOrder(?OrderDomainObject $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): ?OrderDomainObject
    {
        return $this->order;
    }

    public function setProductPrice(?ProductPriceDomainObject $productPrice): self
    {
        $this->productPrice = $productPrice;

        return $this;
    }

    public function getProductPrice(): ?ProductPriceDomainObject
    {
        return $this->productPrice;
    }

    public function setEventOccurrence(?EventOccurrenceDomainObject $eventOccurrence): self
    {
        $this->eventOccurrence = $eventOccurrence;

        return $this;
    }

    public function getEventOccurrence(): ?EventOccurrenceDomainObject
    {
        return $this->eventOccurrence;
    }
}
