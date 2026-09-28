<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class CashlessTransactionDomainObject extends Generated\CashlessTransactionDomainObjectAbstract implements IsSortable
{
    /** @var Collection<CashlessTransactionItemDomainObject>|null */
    private ?Collection $items = null;

    private ?CashlessSalesPointDomainObject $salesPoint = null;

    private ?CashlessWalletDomainObject $wallet = null;

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
                self::CREATED_AT => [
                    'desc' => __('Newest first'),
                    'asc' => __('Oldest first'),
                ],
                self::AMOUNT => [
                    'desc' => __('Largest amount first'),
                    'asc' => __('Smallest amount first'),
                ],
            ]
        );
    }

    public function getItems(): ?Collection
    {
        return $this->items;
    }

    public function setItems(?Collection $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function getSalesPoint(): ?CashlessSalesPointDomainObject
    {
        return $this->salesPoint;
    }

    public function setSalesPoint(?CashlessSalesPointDomainObject $salesPoint): static
    {
        $this->salesPoint = $salesPoint;

        return $this;
    }

    public function getWallet(): ?CashlessWalletDomainObject
    {
        return $this->wallet;
    }

    public function setWallet(?CashlessWalletDomainObject $wallet): static
    {
        $this->wallet = $wallet;

        return $this;
    }

    public function isReversal(): bool
    {
        return $this->getType() === CashlessTransactionType::REVERSAL->value;
    }

    public function isClosure(): bool
    {
        return $this->getType() === CashlessTransactionType::CLOSURE->value;
    }
}
