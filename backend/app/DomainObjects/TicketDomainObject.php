<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use LogicException;
use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;

class TicketDomainObject extends Generated\TicketDomainObjectAbstract implements IsSortable
{
    private ?Collection $taxAndFees = null;

    private ?Collection $prices = null;

    public static function getDefaultSort(): string
    {
        return self::ORDER;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::ORDER => [
                    'asc' => __('Homepage order'),
                ],
                self::CREATED_AT => [
                    'asc' => __('Oldest first'),
                    'desc' => __('Newest first'),
                ],
                self::TITLE => [
                    'asc' => __('Title A-Z'),
                    'desc' => __('Title Z-A'),
                ],
                self::SALE_START_DATE => [
                    'asc' => __('Sale start date closest'),
                    'desc' => __('Sale start date furthest'),
                ],
                self::SALE_END_DATE => [
                    'asc' => __('Sale end date closest'),
                    'desc' => __('Sale end date furthest'),
                ],
            ]
        );
    }

    public function setTaxAndFees(Collection $taxes): TicketDomainObject
    {
        $this->taxAndFees = $taxes;
        return $this;
    }

    public function getTaxRates(): ?Collection
    {
        return $this->getTaxAndFees()?->filter(fn(TaxAndFeesDomainObject $taxAndFee) => $taxAndFee->isTax());
    }

    public function getTaxAndFees(): ?Collection
    {
        return $this->taxAndFees;
    }

    public function getFees(): ?Collection
    {
        return $this->getTaxAndFees()?->filter(fn(TaxAndFeesDomainObject $taxAndFee) => $taxAndFee->isFee());
    }

    public function isSoldOut(): bool
    {
        if (!$this->getTicketPrices() || $this->getTicketPrices()->isEmpty()) {
            throw new LogicException('You cannot check if a ticket is sold out without prices.');
        }

        return $this->getTicketPrices()->every(fn(TicketPriceDomainObject $price) => $price->isSoldOut());
    }

    public function getQuantityAvailable(): int
    {
        if (!$this->getTicketPrices() || $this->getTicketPrices()->isEmpty()) {
            throw new LogicException('You cannot get the quantity available for a ticket without prices.');
        }

        return $this->getTicketPrices()->sum(fn(TicketPriceDomainObject $price) => $price->getQuantityAvailable());
    }

    public function isBeforeSaleStartDate(): bool
    {
        return (!is_null($this->getSaleStartDate())
            && (new Carbon($this->getSaleStartDate()))->isFuture()
        );
    }

    public function isAfterSaleEndDate(): bool
    {
        return (!is_null($this->getSaleEndDate())
            && (new Carbon($this->getSaleEndDate()))->isPast()
        );
    }

    public function isAvailable(): bool
    {
        // If all prices are hidden, it's not available
        if ($this->getType() === TicketType::TIERED->name && $this->getTicketPrices()->isEmpty()) {
            return false;
        }

        return !$this->isSoldOut()
            && !$this->isBeforeSaleStartDate()
            && !$this->isAfterSaleEndDate()
            && !$this->getIsHidden();
    }

    /**
     * @return Collection<TicketPriceDomainObject>|null
     */
    public function getTicketPrices(): ?Collection
    {
        return $this->prices;
    }

    public function setTicketPrices(?Collection $prices): self
    {
        $this->prices = $prices;

        return $this;
    }

    /**
     * All ticket types except TIERED have a single price, so we can just return the first price.
     *
     * @return float|null
     */
    public function getPrice(): ?float
    {
        if ($this->getType() === TicketType::TIERED->name) {
            throw new LogicException('You cannot get a single price for a tiered ticket. Use getPrices() instead.');
        }

        return $this->getTicketPrices()?->first()->getPrice();
    }

    public function getPriceById(int $priceId): ?TicketPriceDomainObject
    {
        return $this->getTicketPrices()?->first(fn(TicketPriceDomainObject $price) => $price->getId() === $priceId);
    }

    public function isTieredType(): bool
    {
        return $this->getType() === TicketType::TIERED->name;
    }

    public function isDonationType(): bool
    {
        return $this->getType() === TicketType::DONATION->name;
    }

    public function isPaidType(): bool
    {
        return $this->getType() === TicketType::PAID->name;
    }

    public function isFreeType(): bool
    {
        return $this->getType() === TicketType::FREE->name;
    }

    public function getInitialQuantityAvailable(): ?int
    {
        if ($this->getType() === TicketType::TIERED->name) {
            return $this->getTicketPrices()?->sum(fn(TicketPriceDomainObject $price) => $price->getInitialQuantityAvailable());
        }

        return $this->getTicketPrices()?->first()?->getInitialQuantityAvailable();
    }

    public function getQuantitySold(): int
    {
        return $this->getTicketPrices()?->sum(fn(TicketPriceDomainObject $price) => $price->getQuantitySold()) ?? 0;
    }
}
