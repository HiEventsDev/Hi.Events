<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use Carbon\Carbon;
use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedOrderCompletionGuard;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderProductDataDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CreatedProductDataDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\OrderQuestionsDTO;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
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
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AffiliateRepositoryInterface $affiliateRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswersRepository,
        private readonly ProductQuantityUpdateService $productQuantityUpdateService,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly CheckoutSessionManagementService $sessionManagementService,
        private readonly OccurrenceStatusValidator $occurrenceStatusValidator,
        private readonly SeatClaimService $seatClaimService,
        private readonly SeatedOrderCompletionGuard $seatedOrderCompletionGuard,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly TransactionLockService $transactionLockService,
    ) {}

    /**
     * @throws ResourceNotFoundException|ResourceConflictException|RuntimeException
     */
    public function handle(string $orderShortId, CompleteOrderDTO $orderData): OrderDomainObject
    {
        /** @var EventSettingDomainObject $eventSettings */
        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $orderData->event_id,
        ]);

        $updatedOrder = DB::transaction(function () use ($orderData, $orderShortId, $eventSettings) {
            $orderDTO = $orderData->order;

            $this->transactionLockService->lockOrder($orderShortId);

            $order = $this->getOrder($orderShortId);

            $this->occurrenceStatusValidator->assertOrderOccurrencesArePurchasable($order);

            $this->seatedOrderCompletionGuard->assertSeatsHeld($order);

            $updatedOrder = $this->updateOrder($order, $orderDTO);

            $this->createAttendees($orderData->products, $order, $orderDTO, $eventSettings);

            if ($orderData->order->questions) {
                $this->createOrderQuestions($orderDTO->questions, $order);
            }

            if (! $order->isPaymentRequired()) {
                $this->productQuantityUpdateService->updateQuantitiesFromOrder($updatedOrder);
            }

            return $updatedOrder;
        });

        event(new OrderStatusChangedEvent(
            order: $updatedOrder,
            sendEmails: true,
            createInvoice: $eventSettings->getEnableInvoicing(),
        ));

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
     * @param  Collection<CompleteOrderProductDataDTO>  $orderProducts
     *
     * @throws Exception
     */
    private function createAttendees(
        Collection $orderProducts,
        OrderDomainObject $order,
        CompleteOrderOrderDTO $orderDTO,
        EventSettingDomainObject $eventSettings,
    ): void {
        $inserts = [];
        $createdProductData = collect();

        $productsPrices = $this->productPriceRepository->findWhereIn(
            field: ProductPriceDomainObjectAbstract::ID,
            values: $orderProducts->pluck('product_price_id')->toArray(),
        );

        $this->validateProductPriceIdsMatchOrder($order, $productsPrices);

        $isPerOrderCollection = $eventSettings->getAttendeeDetailsCollectionMethod() === AttendeeDetailsCollectionMethod::PER_ORDER->name;
        $this->validateTicketProductsCount($order, $orderProducts);

        $unassignedSeatClaims = $this->eventSeatMapLookup->existsForEvent($order->getEventId())
            ? $this->seatClaimService->claimsForOrder($order->getId())
            : collect();
        $orderHasSeatClaims = $unassignedSeatClaims->isNotEmpty();
        $unseatedRemainingByItem = $order->getOrderItems()
            ->mapWithKeys(fn (OrderItemDomainObject $item) => [$item->getId() => $item->getQuantity()
                - $unassignedSeatClaims->filter(fn (SeatClaimDomainObject $claim) => $claim->getOrderItemId() === $item->getId())->count()]);

        foreach ($orderProducts as $attendee) {
            $productId = $productsPrices->first(
                fn (ProductPriceDomainObject $productPrice) => $productPrice->getId() === $attendee->product_price_id)
                ->getProductId();
            $productType = $this->getProductTypeFromPriceId($attendee->product_price_id, $order->getOrderItems());

            if ($productType !== ProductType::TICKET->name) {
                $createdProductData->push(new CreatedProductDataDTO(
                    productRequestData: $attendee,
                    shortId: null,
                ));

                continue;
            }

            $seatClaim = $this->takeSeatClaim($unassignedSeatClaims, $attendee);
            $orderItem = $seatClaim === null
                ? $this->takeUnseatedOrderItem($order, $unseatedRemainingByItem, $attendee->product_price_id)
                : $order->getOrderItems()->first(fn (OrderItemDomainObject $item) => $item->getId() === $seatClaim->getOrderItemId());

            if ($orderItem === null) {
                throw new ResourceConflictException(__('The selected seats do not match the tickets in this order'));
            }

            $shortId = IdHelper::shortId(IdHelper::ATTENDEE_PREFIX);

            $inserts[] = [
                AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
                AttendeeDomainObjectAbstract::PRODUCT_ID => $productId,
                AttendeeDomainObjectAbstract::PRODUCT_PRICE_ID => $attendee->product_price_id,
                AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $orderItem->getEventOccurrenceId(),
                AttendeeDomainObjectAbstract::STATUS => $order->isPaymentRequired()
                    ? AttendeeStatus::AWAITING_PAYMENT->name
                    : AttendeeStatus::ACTIVE->name,
                AttendeeDomainObjectAbstract::EMAIL => $isPerOrderCollection ? $orderDTO->email : $attendee->email,
                AttendeeDomainObjectAbstract::FIRST_NAME => $isPerOrderCollection ? $orderDTO->first_name : $attendee->first_name,
                AttendeeDomainObjectAbstract::LAST_NAME => $isPerOrderCollection ? $orderDTO->last_name : $attendee->last_name,
                AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
                AttendeeDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ATTENDEE_PREFIX),
                AttendeeDomainObjectAbstract::SHORT_ID => $shortId,
                AttendeeDomainObjectAbstract::LOCALE => $order->getLocale(),
                AttendeeDomainObjectAbstract::SEAT_UID => $seatClaim?->getSeatUid(),
                AttendeeDomainObjectAbstract::SEAT_LABEL => $seatClaim?->getSeatLabel(),
            ];

            $createdProductData->push(new CreatedProductDataDTO(
                productRequestData: $attendee,
                shortId: $shortId,
            ));
        }

        if ($unassignedSeatClaims->isNotEmpty()) {
            throw new ResourceConflictException(__('The selected seats do not match the tickets in this order'));
        }

        if (! $this->attendeeRepository->insert($inserts)) {
            throw new RuntimeException(__('Failed to create attendee'));
        }

        if ($orderHasSeatClaims) {
            $this->seatClaimService->pairWithAttendees($order->getId());
        }

        $this->createProductQuestions(
            createdAttendees: $createdProductData,
            order: $order,
            productPrices: $productsPrices,
        );
    }

    /**
     * @param  Collection<SeatClaimDomainObject>  $unassignedSeatClaims
     *
     * @throws ResourceConflictException
     */
    private function takeSeatClaim(Collection $unassignedSeatClaims, CompleteOrderProductDataDTO $attendee): ?SeatClaimDomainObject
    {
        $key = $unassignedSeatClaims->search(
            fn (SeatClaimDomainObject $claim) => $claim->getProductPriceId() === $attendee->product_price_id
                && ($attendee->seat_uid === null || $claim->getSeatUid() === $attendee->seat_uid)
        );

        if ($key !== false) {
            return $unassignedSeatClaims->pull($key);
        }

        if ($attendee->seat_uid !== null) {
            throw new ResourceConflictException(__('One of the selected seats does not belong to this order'));
        }

        return null;
    }

    /**
     * @param  Collection<int, int>  $unseatedRemainingByItem
     */
    private function takeUnseatedOrderItem(OrderDomainObject $order, Collection $unseatedRemainingByItem, int $productPriceId): ?OrderItemDomainObject
    {
        $orderItem = $order->getOrderItems()->first(
            fn (OrderItemDomainObject $item) => $item->getProductPriceId() === $productPriceId
                && $unseatedRemainingByItem->get($item->getId(), 0) > 0
        );

        if ($orderItem !== null) {
            $unseatedRemainingByItem->put($orderItem->getId(), $unseatedRemainingByItem->get($orderItem->getId()) - 1);
        }

        return $orderItem;
    }

    private function createOrderQuestions(Collection $questions, OrderDomainObject $order): void
    {
        $questions->each(function (OrderQuestionsDTO $orderQuestionsDTO) use ($order) {
            $answer = $this->extractAnswer($orderQuestionsDTO->response);
            if ($answer === null) {
                return;
            }
            $this->questionAnswersRepository->create([
                'question_id' => $orderQuestionsDTO->question_id,
                'answer' => $answer,
                'order_id' => $order->getId(),
            ]);
        });
    }

    /**
     * @param  Collection<CreatedProductDataDTO>  $createdAttendees
     * @param  Collection<ProductPriceDomainObject>  $productPrices
     *
     * @throws ResourceConflictException|Exception
     */
    private function createProductQuestions(
        Collection $createdAttendees,
        OrderDomainObject $order,
        Collection $productPrices
    ): void {
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
                fn (ProductPriceDomainObject $productPrice) => $productPrice->getId() === $productRequestData->product_price_id
            )->getProductId();

            $insertedAttendee = $newAttendees->first(
                fn (AttendeeDomainObject $attendee) => $attendee->getShortId() === $createdAttendee->shortId,
            );

            foreach ($productRequestData->questions as $question) {
                $answer = $this->extractAnswer($question->response);
                if ($answer === null) {
                    continue;
                }

                $this->questionAnswersRepository->create([
                    'question_id' => $question->question_id,
                    'answer' => $answer,
                    'order_id' => $order->getId(),
                    'product_id' => $productId,
                    'attendee_id' => $insertedAttendee?->getId(),
                ]);
            }
        }
    }

    private function extractAnswer(array $response): mixed
    {
        $answer = array_key_exists('answer', $response) ? $response['answer'] : $response;

        if ($answer === null || $answer === '' || $answer === []) {
            return null;
        }

        return $answer;
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

        if ($order->getSessionId() === null
            || ! $this->sessionManagementService->verifySession($order->getSessionId())) {
            throw new UnauthorizedException(
                __('Sorry, we could not verify your session. Please restart your order.')
            );
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
                    OrderDomainObjectAbstract::OPTED_INTO_MARKETING_AT => $orderDTO->opted_into_marketing
                        ? Carbon::now()
                        : null,
                ]
            );

        if (! $order->isPaymentRequired() && $updatedOrder->getAffiliateId()) {
            $this->affiliateRepository->incrementSales(
                $updatedOrder->getAffiliateId(),
                $updatedOrder->getTotalGross()
            );
        }

        return $updatedOrder;
    }

    /**
     * @throws ResourceConflictException
     */
    private function validateProductPriceIdsMatchOrder(OrderDomainObject $order, Collection $productsPrices): void
    {
        $orderProductPriceIds = $order->getOrderItems()
            ?->map(fn (OrderItemDomainObject $orderItem) => $orderItem->getProductPriceId())->toArray();

        $productsPricesIds = $productsPrices->map(fn (ProductPriceDomainObject $productPrice) => $productPrice->getId());

        if ($productsPricesIds->diff($orderProductPriceIds)->isNotEmpty()) {
            throw new ResourceConflictException(__('There is an unexpected product price ID in the order'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function validateTicketProductsCount(OrderDomainObject $order, Collection $attendees): void
    {
        $orderItems = $order->getOrderItems() ?? collect();

        $orderTicketCountsByPriceId = $orderItems
            ->filter(fn (OrderItemDomainObject $orderItem) => $orderItem->getProductType() === ProductType::TICKET->name)
            ->groupBy(fn (OrderItemDomainObject $orderItem) => $orderItem->getProductPriceId())
            ->map(fn (Collection $items) => $items->sum(fn (OrderItemDomainObject $item) => $item->getQuantity()));

        $attendeeTicketCountsByPriceId = $attendees
            ->filter(
                fn (CompleteOrderProductDataDTO $attendee) => $this->getProductTypeFromPriceId(
                    $attendee->product_price_id,
                    $orderItems
                ) === ProductType::TICKET->name)
            ->countBy(fn (CompleteOrderProductDataDTO $attendee) => $attendee->product_price_id);

        foreach ($orderTicketCountsByPriceId as $priceId => $expectedCount) {
            if (($attendeeTicketCountsByPriceId[$priceId] ?? 0) !== $expectedCount) {
                throw new ResourceConflictException(
                    __('The number of attendees does not match the number of tickets in the order')
                );
            }
        }
    }

    private function getProductTypeFromPriceId(int $priceId, Collection $orderItems): string
    {
        return $orderItems->first(fn (OrderItemDomainObject $orderItem) => $orderItem->getProductPriceId() === $priceId)
            ->getProductType();
    }
}
