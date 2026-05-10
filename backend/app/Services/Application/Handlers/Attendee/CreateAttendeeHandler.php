<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use Brick\Money\Money;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\InvalidProductPriceId;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CreateAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CreateAttendeeTaxAndFeeDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\Tax\TaxAndFeeRollupService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $eventOccurrenceRepository,
        private readonly ProductQuantityUpdateService $productQuantityAdjustmentService,
        private readonly DatabaseManager $databaseManager,
        private readonly TaxAndFeeRepositoryInterface $taxAndFeeRepository,
        private readonly TaxAndFeeRollupService $taxAndFeeRollupService,
        private readonly OrderManagementService $orderManagementService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly OccurrencePurchaseEligibilityService $occurrenceEligibilityService,
        private readonly OrderAuditLogService $orderAuditLogService,
    ) {}

    /**
     * @throws NoTicketsAvailableException
     * @throws Throwable
     */
    public function handle(CreateAttendeeDTO $attendeeDTO): AttendeeDomainObject
    {
        $attendeeDTO = $this->resolveOccurrenceId($attendeeDTO);

        // Same eligibility checks the public checkout runs — manual creation
        // previously bypassed every one of these, letting organisers issue
        // tickets against cancelled or sold-out occurrences and ignore product
        // visibility rules. The override_capacity flag opts out of the capacity
        // check only and is audited below.
        $this->occurrenceEligibilityService->assertOccurrencePurchasable(
            eventId: $attendeeDTO->event_id,
            occurrenceId: $attendeeDTO->event_occurrence_id,
            additionalQuantity: 1,
            overrideCapacity: $attendeeDTO->override_capacity,
        );
        $this->occurrenceEligibilityService->assertProductsVisibleOnOccurrence(
            $attendeeDTO->event_occurrence_id,
            [$attendeeDTO->product_id],
        );

        return $this->databaseManager->transaction(function () use ($attendeeDTO) {
            $this->calculateTaxesAndFees($attendeeDTO);

            $order = $this->createOrder($attendeeDTO->event_id, $attendeeDTO);

            /** @var ProductDomainObject $product */
            $product = $this->productRepository
                ->loadRelation(ProductPriceDomainObject::class)
                ->findFirstWhere([
                    ProductDomainObjectAbstract::ID => $attendeeDTO->product_id,
                    ProductDomainObjectAbstract::EVENT_ID => $attendeeDTO->event_id,
                    ProductDomainObjectAbstract::PRODUCT_TYPE => ProductType::TICKET->name,
                ]);

            if (! $product) {
                throw new NoTicketsAvailableException(__('This ticket is invalid'));
            }

            $productPriceId = $this->getProductPriceId($attendeeDTO, $product);

            $availableQuantity = $this->productRepository->getQuantityRemainingForProductPrice(
                $attendeeDTO->product_id,
                $productPriceId,
            );

            if ($availableQuantity <= 0) {
                throw new NoTicketsAvailableException(__('There are no tickets available. '.
                    'If you would like to assign a product to this attendee,'.
                    ' please adjust the product\'s available quantity.'));
            }

            $this->processTaxesAndFees($attendeeDTO);

            $orderItem = $this->createOrderItem($attendeeDTO, $order, $product, $productPriceId);

            // Use the resolved $productPriceId (not $attendeeDTO->product_price_id)
            // so the attendee row and inventory adjustment match the order item.
            // The DTO field is nullable — direct API callers can omit it and
            // getProductPriceId() falls back to the product's first price.
            $attendee = $this->createAttendee($order, $attendeeDTO, $productPriceId);

            $this->orderManagementService->updateOrderTotals($order, collect([$orderItem]));

            $this->fireEventsAndUpdateQuantities($attendeeDTO, $order, $productPriceId);

            $this->queueWebhooks($order);

            if ($attendeeDTO->override_capacity) {
                $this->orderAuditLogService->logManualAttendeeCapacityOverride(
                    eventId: $attendeeDTO->event_id,
                    orderId: $order->getId(),
                    attendeeId: $attendee->getId(),
                    occurrenceId: $attendeeDTO->event_occurrence_id,
                    ipAddress: $attendeeDTO->client_ip ?? '',
                    userAgent: $attendeeDTO->client_user_agent,
                );
            }

            return $attendee;
        });
    }

    private function createOrder(int $eventId, CreateAttendeeDTO $attendeeDTO): OrderDomainObject
    {
        $event = $this->eventRepository->findById($eventId);
        $total = Money::of($attendeeDTO->amount_paid, $event->getCurrency());

        return $this->orderRepository->create(
            [
                OrderDomainObjectAbstract::TOTAL_GROSS => $total->getAmount()->toFloat(),
                OrderDomainObjectAbstract::FIRST_NAME => $attendeeDTO->first_name,
                OrderDomainObjectAbstract::LAST_NAME => $attendeeDTO->last_name,
                OrderDomainObjectAbstract::EMAIL => $attendeeDTO->email,
                OrderDomainObjectAbstract::EVENT_ID => $eventId,
                OrderDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::ORDER_PREFIX),
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_STATUS => $total->isZero()
                    ? OrderPaymentStatus::NO_PAYMENT_REQUIRED->name
                    : OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::CURRENCY => $event->getCurrency(),
                OrderDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ORDER_PREFIX),
                OrderDomainObjectAbstract::IS_MANUALLY_CREATED => true,
                OrderDomainObjectAbstract::LOCALE => $attendeeDTO->locale,
            ]
        );
    }

    /**
     * @throws InvalidProductPriceId
     */
    private function getProductPriceId(CreateAttendeeDTO $attendeeDTO, ProductDomainObject $product): int
    {
        $priceIds = $product->getProductPrices()->map(fn (ProductPriceDomainObject $productPrice) => $productPrice->getId());

        if ($attendeeDTO->product_price_id) {
            if (! $priceIds->contains($attendeeDTO->product_price_id)) {
                throw new InvalidProductPriceId(__('The product price ID is invalid.'));
            }

            return $attendeeDTO->product_price_id;
        }

        /** @var ProductPriceDomainObject $productPrice */
        $productPrice = $product->getProductPrices()->first();

        if ($productPrice) {
            return $productPrice->getId();
        }

        throw new InvalidProductPriceId(__('The product price ID is invalid.'));
    }

    private function calculateTaxesAndFees(CreateAttendeeDTO $attendeeDTO): ?Collection
    {
        if (! $attendeeDTO->taxes_and_fees) {
            return null;
        }

        $taxesAndFees = $this->taxAndFeeRepository->findWhereIn(
            'id',
            $attendeeDTO
                ->taxes_and_fees
                ->map(fn (CreateAttendeeTaxAndFeeDTO $taxAndFee) => $taxAndFee->tax_or_fee_id)
                ->toArray()
        );

        $validatedTaxesAndFees = collect();
        $attendeeDTO->taxes_and_fees->each(function (CreateAttendeeTaxAndFeeDTO $taxAndFee) use ($validatedTaxesAndFees, $taxesAndFees) {
            $taxOrFee = $taxesAndFees->first(fn ($taxOrFee) => $taxOrFee->getId() === $taxAndFee->tax_or_fee_id);

            if (! $taxOrFee) {
                throw ValidationException::withMessages([
                    'taxes_and_fees' => __('One or more selected taxes or fees could not be found.'),
                ]);
            }

            $validatedTaxesAndFees->push($taxOrFee);
        });

        return $validatedTaxesAndFees;
    }

    private function processTaxesAndFees(CreateAttendeeDTO $attendeeDTO): void
    {
        $this->calculateTaxesAndFees($attendeeDTO)
            ?->each(fn ($taxOrFee) => $this->taxAndFeeRollupService
                ->addToRollUp(
                    $taxOrFee,
                    $attendeeDTO
                        ->taxes_and_fees
                        ->first(fn ($taxOrFeeDTO) => $taxOrFeeDTO->tax_or_fee_id === $taxOrFee->getId())
                        ->amount)
            );
    }

    private function createOrderItem(CreateAttendeeDTO $attendeeDTO, OrderDomainObject $order, ProductDomainObject $product, int $productPriceId): OrderItemDomainObject
    {
        return $this->orderRepository->addOrderItem(
            [
                OrderItemDomainObjectAbstract::PRODUCT_ID => $attendeeDTO->product_id,
                OrderItemDomainObjectAbstract::QUANTITY => 1,
                OrderItemDomainObjectAbstract::TOTAL_BEFORE_ADDITIONS => $attendeeDTO->amount_paid,
                OrderItemDomainObjectAbstract::TOTAL_GROSS => $attendeeDTO->amount_paid + $this->taxAndFeeRollupService->getTotalTaxesAndFees(),
                OrderItemDomainObjectAbstract::TOTAL_TAX => $this->taxAndFeeRollupService->getTotalTaxes(),
                OrderItemDomainObjectAbstract::TOTAL_SERVICE_FEE => $this->taxAndFeeRollupService->getTotalFees(),
                OrderItemDomainObjectAbstract::PRICE => $attendeeDTO->amount_paid,
                OrderItemDomainObjectAbstract::ORDER_ID => $order->getId(),
                OrderItemDomainObjectAbstract::ITEM_NAME => $product->getTitle(),
                OrderItemDomainObjectAbstract::PRODUCT_PRICE_ID => $productPriceId,
                OrderItemDomainObjectAbstract::TAXES_AND_FEES_ROLLUP => $this->taxAndFeeRollupService->getRollUp(),
                OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID => $attendeeDTO->event_occurrence_id,
            ]
        );
    }

    private function createAttendee(OrderDomainObject $order, CreateAttendeeDTO $attendeeDTO, int $productPriceId): AttendeeDomainObject
    {
        return $this->attendeeRepository->create([
            AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
            AttendeeDomainObjectAbstract::PRODUCT_ID => $attendeeDTO->product_id,
            AttendeeDomainObjectAbstract::PRODUCT_PRICE_ID => $productPriceId,
            AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::ACTIVE->name,
            AttendeeDomainObjectAbstract::EMAIL => $attendeeDTO->email,
            AttendeeDomainObjectAbstract::FIRST_NAME => $attendeeDTO->first_name,
            AttendeeDomainObjectAbstract::LAST_NAME => $attendeeDTO->last_name,
            AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
            AttendeeDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ATTENDEE_PREFIX),
            AttendeeDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::ATTENDEE_PREFIX),
            AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $attendeeDTO->event_occurrence_id,
            AttendeeDomainObjectAbstract::LOCALE => $attendeeDTO->locale,
        ]);
    }

    private function fireEventsAndUpdateQuantities(CreateAttendeeDTO $attendeeDTO, OrderDomainObject $order, int $productPriceId): void
    {
        $this->productQuantityAdjustmentService->increaseQuantitySold(
            priceId: $productPriceId,
            eventOccurrenceId: $attendeeDTO->event_occurrence_id,
        );

        event(new OrderStatusChangedEvent(
            order: $order,
            sendEmails: $attendeeDTO->send_confirmation_email,
        ));
    }

    private function queueWebhooks(OrderDomainObject $order): void
    {
        $this->domainEventDispatcherService->dispatch(
            new OrderEvent(DomainEventType::ORDER_CREATED, $order->getId())
        );
    }

    private function resolveOccurrenceId(CreateAttendeeDTO $attendeeDTO): CreateAttendeeDTO
    {
        if ($attendeeDTO->event_occurrence_id !== null) {
            return $attendeeDTO;
        }

        $event = $this->eventRepository->findById($attendeeDTO->event_id);

        if ($event->getType() !== EventType::SINGLE->name) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('An occurrence must be selected for recurring events.'),
            ]);
        }

        $occurrence = $this->eventOccurrenceRepository->findFirstWhere([
            'event_id' => $attendeeDTO->event_id,
        ]);

        if (!$occurrence) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('No occurrence found for this event.'),
            ]);
        }

        return CreateAttendeeDTO::fromArray(array_merge(
            $attendeeDTO->toArray(),
            ['event_occurrence_id' => $occurrence->getId()]
        ));
    }
}
