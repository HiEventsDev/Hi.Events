<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class CheckInListDomainObject extends Generated\CheckInListDomainObjectAbstract implements IsSortable
{
    private ?Collection $tickets = null;

    private ?EventDomainObject $event = null;

    private ?int $checkedInCount = null;

    private ?int $totalAttendeesCount = null;

    private ?string $timezone = null;

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
                self::EXPIRES_AT => [
                    'asc' => __('Expires soonest'),
                    'desc' => __('Expires latest'),
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

    public function getTickets(): ?Collection
    {
        return $this->tickets;
    }

    public function setTickets(?Collection $tickets): static
    {
        $this->tickets = $tickets;

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

    public function isExpired(string $timezone): bool
    {
        if ($this->getExpiresAt() === null) {
            return false;
        }
        $endDate = Carbon::parse($this->getExpiresAt());
        $endDate->setTimezone($timezone);

        return $endDate->isPast();
    }

    public function isActivated(string $timezone): bool
    {
        if ($this->getActivatesAt() === null) {
            return true;
        }
        $startDate = Carbon::parse($this->getActivatesAt());
        $startDate->setTimezone($timezone);

        return $startDate->isPast();
    }

    public function getCheckedInCount(): ?int
    {
        return $this->checkedInCount;
    }

    public function setCheckedInCount(?int $checkedInCount): static
    {
        $this->checkedInCount = $checkedInCount ?? 0;

        return $this;
    }

    public function getTotalAttendeesCount(): ?int
    {
        return $this->totalAttendeesCount;
    }

    public function setTotalAttendeesCount(?int $totalAttendeesCount): static
    {
        $this->totalAttendeesCount = $totalAttendeesCount ?? 0;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }
}
