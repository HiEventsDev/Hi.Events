<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\Helper\StringHelper;
use HiEvents\Helper\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EventDomainObject extends Generated\EventDomainObjectAbstract implements IsSortable, IsFilterable
{
    private ?Collection $tickets = null;

    private ?Collection $questions = null;

    private ?Collection $images = null;

    private ?Collection $promoCodes = null;

    private ?EventSettingDomainObject $settings = null;

    private ?OrganizerDomainObject $organizer = null;

    public static function getAllowedFilterFields(): array
    {
        return [
            self::TITLE,
            self::START_DATE,
            self::END_DATE,
            self::CREATED_AT,
            self::UPDATED_AT,
            self::STATUS,
            self::ORGANIZER_ID,
        ];
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::START_DATE => [
                    'asc' => __('Closest start date'),
                    'desc' => __('Furthest start date'),
                ],
                self::END_DATE => [
                    'asc' => __('Closest end date'),
                    'desc' => __('Furthest end date'),
                ],
                self::CREATED_AT => [
                    'desc' => __('Newest first'),
                    'asc' => __('Oldest first'),
                ],
                self::UPDATED_AT => [
                    'desc' => __('Recently Updated'),
                    'asc' => __('Least Recently Updated'),
                ],
            ]
        );
    }

    public static function getDefaultSort(): string
    {
        return self::START_DATE;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public function setTickets(Collection $tickets): self
    {
        $this->tickets = $tickets;

        return $this;
    }

    public function getTickets(): ?Collection
    {
        return $this->tickets;
    }

    public function setQuestions(?Collection $questions): EventDomainObject
    {
        $this->questions = $questions;
        return $this;
    }

    public function getQuestions(): ?Collection
    {
        return $this->questions;
    }

    public function getSlug(): string
    {
        return Str::slug($this->getTitle());
    }

    public function setImages(?Collection $images): EventDomainObject
    {
        $this->images = $images;
        return $this;
    }

    public function getImages(): ?Collection
    {
        return $this->images;
    }

    public function getEventSettings(): ?EventSettingDomainObject
    {
        return $this->settings;
    }

    public function setEventSettings(?EventSettingDomainObject $settings): EventDomainObject
    {
        $this->settings = $settings;
        return $this;
    }

    public function getOrganizer(): ?OrganizerDomainObject
    {
        return $this->organizer;
    }

    public function setOrganizer(?OrganizerDomainObject $organizer): self
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getEventUrl(): string
    {
        return sprintf(
            Url::getFrontEndUrlFromConfig(Url::EVENT_HOMEPAGE),
            $this->getId(),
            $this->getSlug()
        );
    }

    public function getDescriptionPreview(): string
    {
        if ($this->getDescription() === null) {
            return '';
        }

        return StringHelper::previewFromHtml($this->getDescription());
    }

    public function isEventInPast(): bool
    {
        if ($this->getEndDate() === null) {
            return false;
        }
        $endDate = Carbon::parse($this->getEndDate());
        $endDate->setTimezone($this->getTimezone());

        return $endDate->isPast();
    }

    public function isEventInFuture(): bool
    {
        if ($this->getStartDate() === null) {
            return false;
        }
        $startDate = Carbon::parse($this->getStartDate());
        $startDate->setTimezone($this->getTimezone());

        return $startDate->isFuture();
    }

    public function isEventOngoing(): bool
    {
        $startDate = Carbon::parse($this->getStartDate());
        $startDate->setTimezone($this->getTimezone());

        if ($this->getEndDate() === null) {
            return $startDate->isPast();
        }

        $endDate = Carbon::parse($this->getEndDate());
        $endDate->setTimezone($this->getTimezone());

        return $startDate->isPast() && $endDate->isFuture();
    }

    public function getLifecycleStatus(): string
    {
        if ($this->isEventInPast()) {
            return EventLifecycleStatus::ENDED->name;
        }

        if ($this->isEventInFuture()) {
            return EventLifecycleStatus::UPCOMING->name;
        }

        if ($this->isEventOngoing()) {
            return EventLifecycleStatus::ONGOING->name;
        }

        return EventLifecycleStatus::ENDED->name;
    }

    public function getPromoCodes(): ?Collection
    {
        return $this->promoCodes;
    }

    public function setPromoCodes(?Collection $promoCodes): self
    {
        $this->promoCodes = $promoCodes;

        return $this;
    }
}
