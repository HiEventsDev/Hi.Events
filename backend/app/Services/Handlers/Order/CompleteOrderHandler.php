<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Order;

use Carbon\Carbon;
use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketPriceDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Ticket\TicketQuantityUpdateService;
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
readonly class CompleteOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface          $orderRepository,
        private AttendeeRepositoryInterface       $attendeeRepository,
        private QuestionAnswerRepositoryInterface $questionAnswersRepository,
        private TicketQuantityUpdateService       $ticketQuantityUpdateService,
        private TicketPriceRepositoryInterface    $ticketPriceRepository,
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
             * If there's no payment required, immediately update the ticket quantities, otherwise handle
             * this in the PaymentIntentEventHandlerService
             *
             * @see PaymentIntentSucceededHandler
             */
            if (!$order->isPaymentRequired()) {
                $this->ticketQuantityUpdateService->updateQuantitiesFromOrder($updatedOrder);
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
        $publicIdIndex = 1;

        $ticketsPrices = $this->ticketPriceRepository->findWhereIn(
            field: TicketPriceDomainObjectAbstract::ID,
            values: $attendees->pluck('ticket_price_id')->toArray(),
        );

        $this->validateTicketPriceIdsMatchOrder($order, $ticketsPrices);

        foreach ($attendees as $attendee) {
            $ticketId = $ticketsPrices->first(
                fn(TicketPriceDomainObject $ticketPrice) => $ticketPrice->getId() === $attendee->ticket_price_id)
                ->getTicketId();

            $inserts[] = [
                AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
                AttendeeDomainObjectAbstract::TICKET_ID => $ticketId,
                AttendeeDomainObjectAbstract::TICKET_PRICE_ID => $attendee->ticket_price_id,
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

        $this->createAttendeeQuestions($attendees, $insertedAttendees, $order, $ticketsPrices);
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
        Collection        $ticketPrices,
    ): void
    {
        $insertedIds = [];
        /** @var CompleteOrderAttendeeDTO $attendee */
        foreach ($attendees as $attendee) {
            $ticketId = $ticketPrices->first(
                fn(TicketPriceDomainObject $ticketPrice) => $ticketPrice->getId() === $attendee->ticket_price_id)
                ->getTicketId();

            $attendeeIterator = $insertedAttendees->filter(
                fn(AttendeeDomainObject $insertedAttendee) => $insertedAttendee->getTicketId() === $ticketId
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
                    'ticket_id' => $ticketId,
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
     * Check if the passed ticket price IDs match what exist in the order_items table
     *
     * @throws ResourceConflictException
     */
    private function validateTicketPriceIdsMatchOrder(OrderDomainObject $order, Collection $ticketsPrices): void
    {
        $orderTicketPriceIds = $order->getOrderItems()
            ?->map(fn(OrderItemDomainObject $orderItem) => $orderItem->getTicketPriceId())->toArray();

        $ticketsPricesIds = $ticketsPrices->map(fn(TicketPriceDomainObject $ticketPrice) => $ticketPrice->getId());

        if ($ticketsPricesIds->diff($orderTicketPriceIds)->isNotEmpty()) {
            throw new ResourceConflictException(__('There is an unexpected ticket price ID in the order'));
        }
    }
}
