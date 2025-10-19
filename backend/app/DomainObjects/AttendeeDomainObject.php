<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class AttendeeDomainObject extends Generated\AttendeeDomainObjectAbstract implements IsSortable, IsFilterable
{
    private ?OrderDomainObject $order = null;

    private ?ProductDomainObject $product = null;

    /** @var Collection<QuestionAndAnswerViewDomainObject>|null */
    public ?Collection $questionAndAnswerViews = null;

    public ?AttendeeCheckInDomainObject $checkIn = null;

    /** @var Collection<AttendeeCheckInDomainObject>|null */
    private ?Collection $checkIns = null;

    public static function getDefaultSort(): string
    {
        return self::CREATED_AT;
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::CREATED_AT => [
                    'asc' => __('Older First'),
                    'desc' => __('Newest First'),
                ],
                self::UPDATED_AT => [
                    'desc' => __('Recently Updated First'),
                    'asc' => __('Recently Updated Last'),
                ],
                self::FIRST_NAME => [
                    'asc' => __('First Name A-Z'),
                    'desc' => __('First Name Z-A'),
                ],
                self::LAST_NAME => [
                    'asc' => __('Last Name A-Z'),
                    'desc' => __('Last Name Z-A'),
                ],
                self::STATUS => [
                    'asc' => __('Status A-Z'),
                    'desc' => __('Status Z-A'),
                ],
            ]
        );
    }

    public static function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    public static function getAllowedFilterFields(): array
    {
        return [
            self::STATUS,
            self::PRODUCT_ID,
        ];
    }

    public function getOrder(): ?OrderDomainObject
    {
        return $this->order;
    }

    public function setOrder(?OrderDomainObject $order): void
    {
        $this->order = $order;
    }

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getProduct(): ?ProductDomainObject
    {
        return $this->product;
    }

    public function setProduct(?ProductDomainObject $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function setQuestionAndAnswerViews(?Collection $questionAndAnswerViews): AttendeeDomainObject
    {
        $this->questionAndAnswerViews = $questionAndAnswerViews;
        return $this;
    }

    public function getQuestionAndAnswerViews(): ?Collection
    {
        return $this->questionAndAnswerViews;
    }

    public function setCheckIn(?AttendeeCheckInDomainObject $checkIn): AttendeeDomainObject
    {
        $this->checkIn = $checkIn;
        return $this;
    }

    /**
     * Only use in the context when a single check-in is expected (e.g., when loading a list of attendees for a specific check-in list).
     *
     * @return AttendeeCheckInDomainObject|null
     */
    public function getCheckIn(): ?AttendeeCheckInDomainObject
    {
        return $this->checkIn;
    }

    public function setCheckIns(?Collection $checkIns): AttendeeDomainObject
    {
        $this->checkIns = $checkIns;
        return $this;
    }

    public function getCheckIns(): ?Collection
    {
        return $this->checkIns;
    }
}
