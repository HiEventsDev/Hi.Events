<?php

namespace HiEvents\DomainObjects;

use HiEvents\Helper\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\Helper\StringHelper;

class EventDomainObject extends Generated\EventDomainObjectAbstract implements IsSortable
{
    private ?Collection $tickets = null;

    private ?Collection $questions = null;

    private ?Collection $images = null;

    private ?EventSettingDomainObject $settings = null;

    private ?OrganizerDomainObject $organizer = null;

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
}
