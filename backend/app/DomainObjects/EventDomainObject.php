<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Helper\StringHelper;
use HiEvents\Helper\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EventDomainObject extends Generated\EventDomainObjectAbstract implements IsFilterable, IsSortable
{
    private ?Collection $products = null;

    private ?Collection $productCategories = null;

    private ?Collection $questions = null;

    private ?Collection $images = null;

    private ?Collection $promoCodes = null;

    private ?Collection $checkInLists = null;

    private ?Collection $webhooks = null;

    private ?Collection $capacityAssignments = null;

    private ?Collection $affiliates = null;

    private ?Collection $eventOccurrences = null;

    private ?EventSettingDomainObject $settings = null;

    private ?OrganizerDomainObject $organizer = null;

    private ?EventStatisticDomainObject $eventStatistics = null;

    private ?AccountDomainObject $account = null;

    private ?EventLocationDomainObject $eventLocation = null;

    private bool $upcomingOccurrencesSoldOut = false;

    private ?string $nextOccurrenceStartDate = null;

    private ?string $lastOccurrenceStartDate = null;

    private ?string $occurrencesMonth = null;

    private ?string $lifecycleStatus = null;

    public static function getAllowedFilterFields(): array
    {
        return [
            self::TITLE,
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
        return self::CREATED_AT;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    public function setProducts(Collection $products): self
    {
        $this->products = $products;

        return $this;
    }

    public function getProducts(): ?Collection
    {
        return $this->products;
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

    public function getAccount(): ?AccountDomainObject
    {
        return $this->account;
    }

    public function setAccount(?AccountDomainObject $account): self
    {
        $this->account = $account;

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

    public function setEventOccurrences(?Collection $eventOccurrences): self
    {
        $this->eventOccurrences = $eventOccurrences;

        return $this;
    }

    public function getEventOccurrences(): ?Collection
    {
        return $this->eventOccurrences;
    }

    public function setUpcomingOccurrencesSoldOut(bool $upcomingOccurrencesSoldOut): self
    {
        $this->upcomingOccurrencesSoldOut = $upcomingOccurrencesSoldOut;

        return $this;
    }

    public function getUpcomingOccurrencesSoldOut(): bool
    {
        return $this->upcomingOccurrencesSoldOut;
    }

    public function getStartDate(): ?string
    {
        if ($this->eventOccurrences === null || $this->eventOccurrences->isEmpty()) {
            return null;
        }

        return $this->eventOccurrences->min(
            fn (EventOccurrenceDomainObject $o) => $o->getStartDate()
        );
    }

    public function getEndDate(): ?string
    {
        if ($this->eventOccurrences === null || $this->eventOccurrences->isEmpty()) {
            return null;
        }

        $withEndDates = $this->eventOccurrences->filter(
            fn (EventOccurrenceDomainObject $o) => $o->getEndDate() !== null
        );

        if ($withEndDates->isEmpty()) {
            return $this->eventOccurrences->max(
                fn (EventOccurrenceDomainObject $o) => $o->getStartDate()
            );
        }

        return $withEndDates->max(
            fn (EventOccurrenceDomainObject $o) => $o->getEndDate()
        );
    }

    public function setNextOccurrenceStartDate(?string $nextOccurrenceStartDate): self
    {
        $this->nextOccurrenceStartDate = $nextOccurrenceStartDate;

        return $this;
    }

    public function setLastOccurrenceStartDate(?string $lastOccurrenceStartDate): self
    {
        $this->lastOccurrenceStartDate = $lastOccurrenceStartDate;

        return $this;
    }

    public function getLastOccurrenceStartDate(): ?string
    {
        return $this->lastOccurrenceStartDate;
    }

    public function setOccurrencesMonth(?string $occurrencesMonth): self
    {
        $this->occurrencesMonth = $occurrencesMonth;

        return $this;
    }

    public function getOccurrencesMonth(): ?string
    {
        return $this->occurrencesMonth;
    }

    public function getNextOccurrenceStartDate(): ?string
    {
        if ($this->nextOccurrenceStartDate !== null) {
            return $this->nextOccurrenceStartDate;
        }

        if ($this->eventOccurrences === null || $this->eventOccurrences->isEmpty()) {
            return null;
        }

        $nextOccurrence = $this->eventOccurrences
            ->filter(fn (EventOccurrenceDomainObject $o) => $o->getStatus() === EventOccurrenceStatus::ACTIVE->name)
            ->filter(fn (EventOccurrenceDomainObject $o) => ! $o->isPast())
            ->sortBy(fn (EventOccurrenceDomainObject $o) => $o->getStartDate())
            ->first();

        return $nextOccurrence?->getStartDate();
    }

    public function isEventOngoing(): bool
    {
        if ($this->eventOccurrences === null || $this->eventOccurrences->isEmpty()) {
            return false;
        }

        return $this->eventOccurrences->contains(
            fn (EventOccurrenceDomainObject $o) => $o->getStatus() === EventOccurrenceStatus::ACTIVE->name
                && ! $o->isFuture()
                && ! $o->isPast()
        );
    }

    public function setLifecycleStatus(string $lifecycleStatus): self
    {
        $this->lifecycleStatus = $lifecycleStatus;

        return $this;
    }

    public function getLifecycleStatus(): string
    {
        if ($this->lifecycleStatus !== null) {
            return $this->lifecycleStatus;
        }

        if ($this->isEventOngoing()) {
            return EventLifecycleStatus::ONGOING->name;
        }

        if ($this->eventOccurrences === null || $this->eventOccurrences->isEmpty()) {
            return EventLifecycleStatus::UPCOMING->name;
        }

        $hasOccurrenceStillToCome = $this->eventOccurrences->contains(
            fn (EventOccurrenceDomainObject $o) => ! $o->isPast()
        );

        return $hasOccurrenceStillToCome
            ? EventLifecycleStatus::UPCOMING->name
            : EventLifecycleStatus::ENDED->name;
    }

    public function isRecurring(): bool
    {
        return $this->getType() === EventType::RECURRING->name;
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

    public function getCheckInLists(): ?Collection
    {
        return $this->checkInLists;
    }

    public function setCheckInLists(?Collection $checkInLists): self
    {
        $this->checkInLists = $checkInLists;

        return $this;
    }

    public function getCapacityAssignments(): ?Collection
    {
        return $this->capacityAssignments;
    }

    public function setCapacityAssignments(?Collection $capacityAssignments): self
    {
        $this->capacityAssignments = $capacityAssignments;

        return $this;
    }

    public function getEventStatistics(): ?EventStatisticDomainObject
    {
        return $this->eventStatistics;
    }

    public function setEventStatistics(?EventStatisticDomainObject $eventStatistics): self
    {
        $this->eventStatistics = $eventStatistics;

        return $this;
    }

    public function setProductCategories(?Collection $productCategories): EventDomainObject
    {
        $this->productCategories = $productCategories;

        return $this;
    }

    public function getProductCategories(): ?Collection
    {
        return $this->productCategories;
    }

    public function getWebhooks(): ?Collection
    {
        return $this->webhooks;
    }

    public function setWebhooks(?Collection $webhooks): EventDomainObject
    {
        $this->webhooks = $webhooks;

        return $this;
    }

    public function getAffiliates(): ?Collection
    {
        return $this->affiliates;
    }

    public function setAffiliates(?Collection $affiliates): EventDomainObject
    {
        $this->affiliates = $affiliates;

        return $this;
    }

    public function getEventLocation(): ?EventLocationDomainObject
    {
        return $this->eventLocation;
    }

    public function setEventLocation(?EventLocationDomainObject $eventLocation): self
    {
        $this->eventLocation = $eventLocation;

        return $this;
    }
}
