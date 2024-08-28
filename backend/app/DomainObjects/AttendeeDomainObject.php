<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class AttendeeDomainObject extends Generated\AttendeeDomainObjectAbstract implements IsSortable, IsFilterable
{
    private ?OrderDomainObject $order = null;

    private ?TicketDomainObject $ticket = null;

    /** @var Collection<QuestionAndAnswerViewDomainObject>|null */
    public ?Collection $questionAndAnswerViews = null;

    public ?AttendeeCheckInDomainObject $checkIn = null;

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
                    'desc' => __('Newer First'),
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
            self::TICKET_ID,
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

    public function getTicket(): ?TicketDomainObject
    {
        return $this->ticket;
    }

    public function setTicket(?TicketDomainObject $ticket): self
    {
        $this->ticket = $ticket;

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

    public function getCheckIn(): ?AttendeeCheckInDomainObject
    {
        return $this->checkIn;
    }
}
