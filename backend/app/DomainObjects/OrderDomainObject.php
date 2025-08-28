<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Helper\AddressHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class OrderDomainObject extends Generated\OrderDomainObjectAbstract implements IsSortable, IsFilterable
{
    /** @var Collection<OrderItemDomainObject>|null */
    public ?Collection $orderItems = null;

    /** @var Collection<AttendeeDomainObject>|null */
    public ?Collection $attendees = null;

    public ?StripePaymentDomainObject $stripePayment = null;

    /** @var Collection<QuestionAndAnswerViewDomainObject>|null */
    public ?Collection $questionAndAnswerViews = null;

    public ?Collection $invoices = null;

    public ?EventDomainObject $event = null;

    public ?string $sessionIdentifier = null;

    public static function getAllowedFilterFields(): array
    {
        return [
            self::STATUS,
            self::PAYMENT_STATUS,
            self::REFUND_STATUS,
            self::CREATED_AT,
            self::FIRST_NAME,
            self::LAST_NAME,
            self::EMAIL,
            self::PUBLIC_ID,
            self::CURRENCY,
            self::TOTAL_GROSS,
        ];
    }

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

    public function getProductOrderItems(): Collection
    {
        if ($this->getOrderItems() === null) {
            return new Collection();
        }

        return $this->getOrderItems()->filter(static function (OrderItemDomainObject $orderItem) {
            return $orderItem->getProductType() === ProductType::GENERAL->name;
        });
    }

    public function getTicketOrderItems(): Collection
    {
        if ($this->getOrderItems() === null) {
            return new Collection();
        }

        return $this->getOrderItems()->filter(static function (OrderItemDomainObject $orderItem) {
            return $orderItem->getProductType() === ProductType::TICKET->name;
        });
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

    public function isOrderAwaitingOfflinePayment(): bool
    {
        return $this->getStatus() === OrderStatus::AWAITING_OFFLINE_PAYMENT->name;
    }

    public function isOrderCompleted(): bool
    {
        return $this->getStatus() === OrderStatus::COMPLETED->name;
    }

    public function isOrderCancelled(): bool
    {
        return $this->getStatus() === OrderStatus::CANCELLED->name;
    }

    public function isOrderReserved(): bool
    {
        return $this->getStatus() === OrderStatus::RESERVED->name;
    }

    public function isReservedOrderExpired(): bool
    {
        return (new Carbon($this->getReservedUntil()))->isPast();
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
        return !$this->isFreeOrder() && ($this->getTotalRefunded() >= $this->getTotalGross());
    }

    public function getHumanReadableStatus(): string
    {
        return OrderStatus::getHumanReadableStatus($this->getStatus());
    }

    public function getBillingAddressString(): string
    {
        return AddressHelper::formatAddress($this->getAddress());
    }

    public function getHasTaxes(): bool
    {
        return $this->getTotalTax() > 0;
    }

    public function getHasFees(): bool
    {
        return $this->getTotalFee() > 0;
    }

    public function getLatestInvoice(): ?InvoiceDomainObject
    {
        return $this->getInvoices()?->sortByDesc(fn(InvoiceDomainObject $invoice) => $invoice->getId())->first();
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

    public function getTotalQuantity(): int
    {
        if ($this->getOrderItems() === null) {
            throw new RuntimeException('Cannot calculate total quantity, order items are null');
        }

        return $this->getOrderItems()->sum(fn(OrderItemDomainObject $item) => $item->getQuantity());
    }

    public function getQuestionAndAnswerViews(): ?Collection
    {
        return $this->questionAndAnswerViews;
    }

    public function setEvent(?EventDomainObject $event): OrderDomainObject
    {
        $this->event = $event;
        return $this;
    }

    public function getEvent(): ?EventDomainObject
    {
        return $this->event;
    }

    public function setInvoices(?Collection $invoices): OrderDomainObject
    {
        $this->invoices = $invoices;
        return $this;
    }

    public function getInvoices(): ?Collection
    {
        return $this->invoices;
    }

    public function setSessionIdentifier(?string $sessionIdentifier): OrderDomainObject
    {
        $this->sessionIdentifier = $sessionIdentifier;
        return $this;
    }

    public function getSessionIdentifier(): ?string
    {
        return $this->sessionIdentifier;
    }

    public function isRefundable(): bool
    {
        return !$this->isFreeOrder()
            && $this->getStatus() !== OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name
            && $this->getPaymentProvider() === PaymentProviders::STRIPE->name
            && $this->getRefundStatus() !== OrderRefundStatus::REFUNDED->name;
    }
}
