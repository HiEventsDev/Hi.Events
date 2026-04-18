<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class ContactDomainObject extends Generated\ContactDomainObjectAbstract implements IsSortable
{
    private ?Collection $attendees = null;

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::CREATED_AT => [
                    'asc' => __('Oldest First'),
                    'desc' => __('Newest First'),
                ],
                self::FIRST_NAME => [
                    'asc' => __('First Name A-Z'),
                    'desc' => __('First Name Z-A'),
                ],
                self::LAST_NAME => [
                    'asc' => __('Last Name A-Z'),
                    'desc' => __('Last Name Z-A'),
                ],
                self::EMAIL => [
                    'asc' => __('Email A-Z'),
                    'desc' => __('Email Z-A'),
                ],
            ],
        );
    }

    public static function getDefaultSort(): string
    {
        return self::CREATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    public function getFullName(): string
    {
        return trim(($this->getFirstName() ?? '') . ' ' . ($this->getLastName() ?? ''));
    }

    public function getAttendees(): ?Collection
    {
        return $this->attendees;
    }

    public function setAttendees(?Collection $attendees): self
    {
        $this->attendees = $attendees;
        return $this;
    }
}
