<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use Carbon\Carbon;
use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderProductDataDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CreatedProductDataDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\OrderQuestionsDTO;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * @todo - Tidy this up
 */
class CompleteOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly AffiliateRepositoryInterface      $affiliateRepository,
        private readonly AttendeeRepositoryInterface       $attendeeRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswersRepository,
        private readonly ProductQuantityUpdateService      $productQuantityUpdateService,
        private readonly ProductPriceRepositoryInterface   $productPriceRepository,
        private readonly DomainEventDispatcherService      $domainEventDispatcherService,
    )
    {
    }

    /**
     * @throws ResourceNotFoundException|ResourceConflictException|RuntimeException
     */
    public function handle(string $orderShortId, CompleteOrderDTO $orderData): OrderDomainObject
    {
        $updatedOrder = DB::transaction(function () use ($orderData, $orderShortId) {
            $orderDTO = $orderData->order;

            $order = $this->getOrder($orderShortId);

            $updatedOrder = $this->updateOrder($order, $orderDTO);

            $this->createAttendees($orderData->products, $order);

            if ($orderData->order->questions) {
                $this->createOrderQuestions($orderDTO->questions, $order);
            }

            /**
             * If there's no payment required, immediately update the product quantities, otherwise handle
             * this in the PaymentIntentEventHandlerService
             *
             * @see PaymentIntentSucceededHandler
             */
            if (!$order->isPaymentRequired()) {
                $this->productQuantityUpdateService->updateQuantitiesFromOrder($updatedOrder);
            }

            return $updatedOrder;
        });

        OrderStatusChangedEvent::dispatch($updatedOrder);

        if ($updatedOrder->isOrderCompleted()) {
            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_CREATED,
                    orderId: $updatedOrder->getId(),
                )
            );
        }

        return $updatedOrder;
    }

    /**
     * @param Collection<CompleteOrderProductDataDTO> $orderProducts
     * @throws Exception
     */
    private function createAttendees(Collection $orderProducts, OrderDomainObject $order): void
    {
        $inserts = [];
        $createdProductData = collect();

        $productsPrices = $this->productPriceRepository->findWhereIn(
            field: ProductPriceDomainObjectAbstract::ID,
            values: $orderProducts->pluck('product_price_id')->toArray(),
        );

        $this->validateProductPriceIdsMatchOrder($order, $productsPrices);
        $this->validateTicketProductsCount($order, $orderProducts);

        foreach ($orderProducts as $attendee) {
            $productId = $productsPrices->first(
                fn(ProductPriceDomainObject $productPrice) => $productPrice->getId() === $attendee->product_price_id)
                ->getProductId();
            $productType = $this->getProductTypeFromPriceId($attendee->product_price_id, $order->getOrderItems());

            // If it's not a ticket, skip, as we only want to create attendees for tickets
            if ($productType !== ProductType::TICKET->name) {
                $createdProductData->push(new CreatedProductDataDTO(
                    productRequestData: $attendee,
                    shortId: null,
                ));

                continue;
            }

            $shortId = IdHelper::shortId(IdHelper::ATTENDEE_PREFIX);

            $inserts[] = [
                AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
                AttendeeDomainObjectAbstract::PRODUCT_ID => $productId,
                AttendeeDomainObjectAbstract::PRODUCT_PRICE_ID => $attendee->product_price_id,
                AttendeeDomainObjectAbstract::STATUS => $order->isPaymentRequired()
                    ? AttendeeStatus::AWAITING_PAYMENT->name
                    : AttendeeStatus::ACTIVE->name,
                AttendeeDomainObjectAbstract::EMAIL => $attendee->email,
                AttendeeDomainObjectAbstract::FIRST_NAME => $attendee->first_name,
                AttendeeDomainObjectAbstract::LAST_NAME => $attendee->last_name,
                AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
                AttendeeDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ATTENDEE_PREFIX),
                AttendeeDomainObjectAbstract::SHORT_ID => $shortId,
                AttendeeDomainObjectAbstract::LOCALE => $order->getLocale(),
            ];

            $createdProductData->push(new CreatedProductDataDTO(
                productRequestData: $attendee,
                shortId: $shortId,
            ));
        }

        if (!$this->attendeeRepository->insert($inserts)) {
            throw new RuntimeException(__('Failed to create attendee'));
        }

        $this->createProductQuestions(
            createdAttendees: $createdProductData,
            order: $order,
            productPrices: $productsPrices,
        );
    }

    private function createOrderQuestions(Collection $questions, OrderDomainObject $order): void
    {
        $questions->each(function (OrderQuestionsDTO $orderQuestionsDTO) use ($order) {
            if (empty($orderQuestionsDTO->response)) {
                return;
            }
            $this->questionAnswersRepository->create([
                'question_id' => $orderQuestionsDTO->question_id,
                'answer' => $orderQuestionsDTO->response['answer'] ?? $orderQuestionsDTO->response,
                'order_id' => $order->getId(),
            ]);
        });
    }

    /**
     * @param Collection<CreatedProductDataDTO> $createdAttendees
     * @param Collection<ProductPriceDomainObject> $productPrices
     * @throws ResourceConflictException|Exception
     */
    private function createProductQuestions(
        Collection        $createdAttendees,
        OrderDomainObject $order,
        Collection        $productPrices
    ): void
    {
        $newAttendees = $this->attendeeRepository->findWhereIn(
            field: AttendeeDomainObjectAbstract::SHORT_ID,
            values: $createdAttendees->pluck('shortId')->toArray(),
        );

        foreach ($createdAttendees as $createdAttendee) {
            $productRequestData = $createdAttendee->productRequestData;

            if ($productRequestData->questions === null) {
                continue;
            }

            $productId = $productPrices->first(
                fn(ProductPriceDomainObject $productPrice) => $productPrice->getId() === $productRequestData->product_price_id
            )->getProductId();

            // This will be null for non-ticket products
            $insertedAttendee = $newAttendees->first(
                fn(AttendeeDomainObject $attendee) => $attendee->getShortId() === $createdAttendee->shortId,
            );

            foreach ($productRequestData->questions as $question) {
                if (empty($question->response)) {
                    continue;
                }

                $this->questionAnswersRepository->create([
                    'question_id' => $question->question_id,
                    'answer' => $question->response['answer'] ?? $question->response,
                    'order_id' => $order->getId(),
                    'product_id' => $productId,
                    'attendee_id' => $insertedAttendee?->getId(),
                ]);
            }
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function validateOrder(OrderDomainObject $order): void
    {
        if ($order->getEmail() !== null) {
            throw new ResourceConflictException(__('This order has already been processed'));
        }

        if (Carbon::createFromTimeString($order->getReservedUntil())->isPast()) {
            throw new ResourceConflictException(__('This order has expired'));
        }

        if ($order->getStatus() !== OrderStatus::RESERVED->name) {
            throw new ResourceConflictException(__('This order has already been processed'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function getOrder(string $orderShortId): OrderDomainObject
    {
        $order = $this->orderRepository
            ->loadRelation(
                new Relationship(
                    domainObject: OrderItemDomainObject::class,
                    nested: [new Relationship(ProductDomainObject::class, name: 'product')]
                ))
            ->findByShortId($orderShortId);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        $this->validateOrder($order);

        return $order;
    }

    private function updateOrder(OrderDomainObject $order, CompleteOrderOrderDTO $orderDTO): OrderDomainObject
    {
        $updatedOrder = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray(
                $order->getId(),
                [
                    OrderDomainObjectAbstract::ADDRESS => $orderDTO->address,
                    OrderDomainObjectAbstract::FIRST_NAME => $orderDTO->first_name,
                    OrderDomainObjectAbstract::LAST_NAME => $orderDTO->last_name,
                    OrderDomainObjectAbstract::EMAIL => $orderDTO->email,
                    OrderDomainObjectAbstract::PAYMENT_STATUS => $order->isPaymentRequired()
                        ? OrderPaymentStatus::AWAITING_PAYMENT->name
                        : OrderPaymentStatus::NO_PAYMENT_REQUIRED->name,
                    OrderDomainObjectAbstract::STATUS => $order->isPaymentRequired()
                        ? OrderStatus::RESERVED->name
                        : OrderStatus::COMPLETED->name,
                ]
            );

        // Update affiliate sales if this is a free order (no payment required) and has an affiliate
        if (!$order->isPaymentRequired() && $updatedOrder->getAffiliateId()) {
            $this->affiliateRepository->incrementSales(
                $updatedOrder->getAffiliateId(),
                $updatedOrder->getTotalGross()
            );
        }

        return $updatedOrder;
    }

    /**
     * Check if the passed product price IDs match what exist in the order_items table
     *
     * @throws ResourceConflictException
     */
    private function validateProductPriceIdsMatchOrder(OrderDomainObject $order, Collection $productsPrices): void
    {
        $orderProductPriceIds = $order->getOrderItems()
            ?->map(fn(OrderItemDomainObject $orderItem) => $orderItem->getProductPriceId())->toArray();

        $productsPricesIds = $productsPrices->map(fn(ProductPriceDomainObject $productPrice) => $productPrice->getId());

        if ($productsPricesIds->diff($orderProductPriceIds)->isNotEmpty()) {
            throw new ResourceConflictException(__('There is an unexpected product price ID in the order'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function validateTicketProductsCount(OrderDomainObject $order, Collection $attendees): void
    {
        $orderAttendeeCount = $order->getOrderItems()
            ?->filter(fn(OrderItemDomainObject $orderItem) => $orderItem->getProductType() === ProductType::TICKET->name)
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity());

        $ticketAttendeeCount = $attendees
            ->filter(
                fn(CompleteOrderProductDataDTO $attendee) => $this->getProductTypeFromPriceId(
                        $attendee->product_price_id,
                        $order->getOrderItems()
                    ) === ProductType::TICKET->name)
            ->count();

        if ($orderAttendeeCount !== $ticketAttendeeCount) {
            throw new ResourceConflictException(
                __('The number of attendees does not match the number of tickets in the order')
            );
        }
    }

    private function getProductTypeFromPriceId(int $priceId, Collection $orderItems): string
    {
        return $orderItems->first(fn(OrderItemDomainObject $orderItem) => $orderItem->getProductPriceId() === $priceId)
            ->getProductType();
    }
}
