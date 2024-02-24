<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;

class OrderDomainObject extends Generated\OrderDomainObjectAbstract implements IsSortable
{
    /** @var Collection<OrderItemDomainObject>|null */
    public ?Collection $orderItems = null;

    /** @var Collection<AttendeeDomainObject>|null */
    public ?Collection $attendees = null;

    public ?StripePaymentDomainObject $stripePayment = null;

    /** @var Collection<QuestionAndAnswerViewDomainObject>|null */
    public ?Collection $questionAndAnswerViews = null;

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts(
            [
                self::CREATED_AT => [
                    'asc' => __('Oldest First'),
                    'desc' => __('Newest First'),
                ],
                self::FIRST_NAME => [
                    'asc' => __('Buyer Name A-Z'),
                    'desc' => __('Buyer Name Z-A'),
                ],
                self::TOTAL_GROSS => [
                    'asc' => __('Amount Ascending'),
                    'desc' => __('Amount Descending'),
                ],
                self::EMAIL => [
                    'asc' => __('Buyer Email A-Z'),
                    'desc' => __('Buyer Email Z-A'),
                ],
                self::PUBLIC_ID => [
                    'asc' => __('Order # Ascending'),
                    'desc' => __('Order # Descending'),
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
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    public function setOrderItems(?Collection $orderItems): OrderDomainObject
    {
        $this->orderItems = $orderItems;
        return $this;
    }

    /**
     * @return Collection<OrderItemDomainObject>|null
     */
    public function getOrderItems(): ?Collection
    {
        return $this->orderItems;
    }

    public function setAttendees(?Collection $attendees): OrderDomainObject
    {
        $this->attendees = $attendees;
        return $this;
    }

    public function getAttendees(): ?Collection
    {
        return $this->attendees;
    }

    public function isPaymentRequired(): bool
    {
        return (int)ceil($this->getTotalGross()) > 0;
    }

    public function isOrderCompleted(): bool
    {
        return $this->getStatus() === OrderStatus::COMPLETED->name;
    }

    public function isOrderCancelled(): bool
    {
        return $this->getStatus() === OrderStatus::CANCELLED->name;
    }

    public function isOrderFailed(): bool
    {
        return $this->getPaymentStatus() === OrderPaymentStatus::PAYMENT_FAILED->name;
    }

    public function setStripePayment(?StripePaymentDomainObject $stripePayment): OrderDomainObject
    {
        $this->stripePayment = $stripePayment;
        return $this;
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->getTotalRefunded() > 0 && $this->getTotalRefunded() < $this->getTotalGross();
    }

    public function isFullyRefunded(): bool
    {
        return $this->getTotalRefunded() >= $this->getTotalGross();
    }

    public function getStripePayment(): ?StripePaymentDomainObject
    {
        return $this->stripePayment;
    }

    public function isFreeOrder(): bool
    {
        return $this->getTotalGross() === 0.00;
    }

    public function setQuestionAndAnswerViews(?Collection $questionAndAnswerViews): OrderDomainObject
    {
        $this->questionAndAnswerViews = $questionAndAnswerViews;
        return $this;
    }

    public function getQuestionAndAnswerViews(): ?Collection
    {
        return $this->questionAndAnswerViews;
    }
}
