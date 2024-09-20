<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Order;

use Carbon\Carbon;
use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Handlers\Order\DTO\CompleteOrderAttendeeDTO;
use HiEvents\Services\Handlers\Order\DTO\CompleteOrderDTO;
use HiEvents\Services\Handlers\Order\DTO\CompleteOrderOrderDTO;
use HiEvents\Services\Handlers\Order\DTO\OrderQuestionsDTO;
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
        private readonly AttendeeRepositoryInterface       $attendeeRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswersRepository,
        private readonly ProductQuantityUpdateService      $productQuantityUpdateService,
        private readonly ProductPriceRepositoryInterface   $productPriceRepository,
    )
    {
    }

    /**
     * @throws ResourceNotFoundException|ResourceConflictException|RuntimeException
     */
    public function handle(string $orderShortId, CompleteOrderDTO $orderData): OrderDomainObject
    {
        return DB::transaction(function () use ($orderData, $orderShortId) {
            $orderDTO = $orderData->order;

            $order = $this->getOrder($orderShortId);

            $updatedOrder = $this->updateOrder($order, $orderDTO);

            $this->createAttendees($orderData->attendees, $order);

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

            OrderStatusChangedEvent::dispatch($updatedOrder);

            return $updatedOrder;
        });
    }

    /**
     * @throws Exception
     */
    private function createAttendees(Collection $attendees, OrderDomainObject $order): void
    {
        $inserts = [];
        
        $productsPrices = $this->productPriceRepository->findWhereIn(
            field: ProductPriceDomainObjectAbstract::ID,
            values: $attendees->pluck('product_price_id')->toArray(),
        );

        $this->validateProductPriceIdsMatchOrder($order, $productsPrices);

        foreach ($attendees as $attendee) {
            $productId = $productsPrices->first(
                fn(ProductPriceDomainObject $productPrice) => $productPrice->getId() === $attendee->product_price_id)
                ->getProductId();

            $inserts[] = [
                AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
                AttendeeDomainObjectAbstract::PRODUCT_ID => $productId,
                AttendeeDomainObjectAbstract::PRODUCT_PRICE_ID => $attendee->product_price_id,
                AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::ACTIVE->name,
                AttendeeDomainObjectAbstract::EMAIL => $attendee->email,
                AttendeeDomainObjectAbstract::FIRST_NAME => $attendee->first_name,
                AttendeeDomainObjectAbstract::LAST_NAME => $attendee->last_name,
                AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
                AttendeeDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ATTENDEE_PREFIX),
                AttendeeDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::ATTENDEE_PREFIX),
                AttendeeDomainObjectAbstract::LOCALE => $order->getLocale(),
            ];
        }

        if (!$this->attendeeRepository->insert($inserts)) {
            throw new RuntimeException(__('Failed to create attendee'));
        }

        $insertedAttendees = $this->attendeeRepository->findWhere([
            AttendeeDomainObjectAbstract::ORDER_ID => $order->getId()
        ]);

        $this->createAttendeeQuestions($attendees, $insertedAttendees, $order, $productsPrices);
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

    private function createAttendeeQuestions(
        Collection        $attendees,
        Collection        $insertedAttendees,
        OrderDomainObject $order,
        Collection        $productPrices,
    ): void
    {
        $insertedIds = [];
        /** @var CompleteOrderAttendeeDTO $attendee */
        foreach ($attendees as $attendee) {
            $productId = $productPrices->first(
                fn(ProductPriceDomainObject $productPrice) => $productPrice->getId() === $attendee->product_price_id)
                ->getProductId();

            $attendeeIterator = $insertedAttendees->filter(
                fn(AttendeeDomainObject $insertedAttendee) => $insertedAttendee->getProductId() === $productId
                    && !in_array($insertedAttendee->getId(), $insertedIds, true)
            )->getIterator();

            if ($attendee->questions === null) {
                continue;
            }

            foreach ($attendee->questions as $question) {
                $attendeeId = $attendeeIterator->current()->getId();

                if (empty($question->response)) {
                    continue;
                }

                $this->questionAnswersRepository->create([
                    'question_id' => $question->question_id,
                    'answer' => $question->response['answer'] ?? $question->response,
                    'order_id' => $order->getId(),
                    'product_id' => $productId,
                    'attendee_id' => $attendeeId
                ]);

                $insertedIds[] = $attendeeId;
            }
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function validateOrder(OrderDomainObject $order): void
    {
        if ($order->getEmail() !== null) {
            throw new ResourceConflictException(__('This order is has already been processed'));
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
            ->loadRelation(OrderItemDomainObject::class)
            ->findByShortId($orderShortId);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        $this->validateOrder($order);

        return $order;
    }

    private function updateOrder(OrderDomainObject $order, CompleteOrderOrderDTO $orderDTO): OrderDomainObject
    {
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray(
                $order->getId(),
                [
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
}
