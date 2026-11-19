<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class CrmContactDomainObject extends Generated\CrmContactDomainObjectAbstract implements IsSortable
{
    private ?Collection $tags = null;

    private ?Collection $spend = null;

    private ?Collection $emails = null;

    private ?Collection $names = null;

    private ?int $noShowCount = null;

    private ?object $suppression = null;

    public static function getDefaultSort(): string
    {
        return static::LAST_ACTIVITY_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::LAST_ACTIVITY_AT => [
                    'desc' => __('Most recently active'),
                    'asc' => __('Least recently active'),
                ],
                self::FIRST_SEEN_AT => [
                    'desc' => __('Newest contacts'),
                    'asc' => __('Oldest contacts'),
                ],
                self::ORDERS_COUNT => [
                    'desc' => __('Most orders'),
                    'asc' => __('Fewest orders'),
                ],
                self::EVENTS_COUNT => [
                    'desc' => __('Most events'),
                    'asc' => __('Fewest events'),
                ],
                self::ATTENDED_COUNT => [
                    'desc' => __('Most check-ins'),
                    'asc' => __('Fewest check-ins'),
                ],
                self::FIRST_NAME => [
                    'asc' => __('Name A-Z'),
                    'desc' => __('Name Z-A'),
                ],
                self::EMAIL => [
                    'asc' => __('Email A-Z'),
                    'desc' => __('Email Z-A'),
                ],
                self::EMAIL_MARKETING_STATUS_CHANGED_AT => [
                    'desc' => __('Email status changed most recently'),
                    'asc' => __('Email status changed longest ago'),
                ],
            ]
        );
    }

    public function getFullName(): string
    {
        return trim(($this->getFirstName() ?? '').' '.($this->getLastName() ?? ''));
    }

    public function getTags(): ?Collection
    {
        return $this->tags;
    }

    public function setTags(?Collection $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function getSpend(): ?Collection
    {
        return $this->spend;
    }

    public function setSpend(?Collection $spend): static
    {
        $this->spend = $spend;

        return $this;
    }

    public function getEmails(): ?Collection
    {
        return $this->emails;
    }

    public function setEmails(?Collection $emails): static
    {
        $this->emails = $emails;

        return $this;
    }

    public function getNames(): ?Collection
    {
        return $this->names;
    }

    public function setNames(?Collection $names): static
    {
        $this->names = $names;

        return $this;
    }

    public function getNoShowCount(): ?int
    {
        return $this->noShowCount;
    }

    public function setNoShowCount(?int $noShowCount): static
    {
        $this->noShowCount = $noShowCount;

        return $this;
    }

    public function getSuppression(): ?object
    {
        return $this->suppression;
    }

    public function setSuppression(?object $suppression): static
    {
        $this->suppression = $suppression;

        return $this;
    }
}
