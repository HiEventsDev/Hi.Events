<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use Illuminate\Support\Collection;

class CashlessWalletDomainObject extends Generated\CashlessWalletDomainObjectAbstract implements IsSortable
{
    private ?AttendeeDomainObject $attendee = null;

    /** @var Collection<CashlessTransactionDomainObject>|null */
    private ?Collection $transactions = null;

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
                self::BALANCE => [
                    'desc' => __('Highest balance first'),
                    'asc' => __('Lowest balance first'),
                ],
                self::TOTAL_SPENT => [
                    'desc' => __('Highest spend first'),
                    'asc' => __('Lowest spend first'),
                ],
                self::CREATED_AT => [
                    'desc' => __('Newest first'),
                    'asc' => __('Oldest first'),
                ],
            ]
        );
    }

    public function getAttendee(): ?AttendeeDomainObject
    {
        return $this->attendee;
    }

    public function setAttendee(?AttendeeDomainObject $attendee): static
    {
        $this->attendee = $attendee;

        return $this;
    }

    public function getTransactions(): ?Collection
    {
        return $this->transactions;
    }

    public function setTransactions(?Collection $transactions): static
    {
        $this->transactions = $transactions;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->getStatus() === CashlessWalletStatus::ACTIVE->value;
    }
}
