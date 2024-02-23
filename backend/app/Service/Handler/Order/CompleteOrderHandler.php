<?php

declare(strict_types=1);

namespace TicketKitten\Service\Handler\Order;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\OrderDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\TicketPriceDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\DomainObjects\Status\AttendeeStatus;
use TicketKitten\DomainObjects\Status\OrderPaymentStatus;
use TicketKitten\DomainObjects\Status\OrderStatus;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Events\OrderStatusChangedEvent;
use TicketKitten\Exceptions\ResourceConflictException;
use TicketKitten\Helper\IdHelper;
use TicketKitten\Http\DataTransferObjects\CompleteOrderAttendeeDTO;
use TicketKitten\Http\DataTransferObjects\CompleteOrderDTO;
use TicketKitten\Http\DataTransferObjects\CompleteOrderOrderDTO;
use TicketKitten\Http\DataTransferObjects\OrderQuestionsDTO;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketPriceRepositoryInterface;
use TicketKitten\Service\Common\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use TicketKitten\Service\Common\Ticket\TicketQuantityUpdateService;

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
                $this->ticketQuantityUpdateService->updateTicketQuantities($updatedOrder);
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
                AttendeeDomainObjectAbstract::PUBLIC_ID => $order->getPublicId() . '-' . $publicIdIndex++,
                AttendeeDomainObjectAbstract::SHORT_ID => IdHelper::randomPrefixedId(IdHelper::ATTENDEE_PREFIX),
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
}
