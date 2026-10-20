<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeOrderItemDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeOrderDTO;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Order\DTO\ProcessedOrderItemDTO;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BoxOfficeOrderCreationService
{
    public const RESERVATION_MINUTES = 30;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly BoxOfficeCartValidationService $cartValidationService,
        private readonly BoxOfficeDiscountFactory $discountFactory,
        private readonly OrderManagementService $orderManagementService,
        private readonly OrderItemProcessingService $orderItemProcessingService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswerRepository,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly SeatClaimService $seatClaimService,
        private readonly SeatingEventLockService $seatingEventLock,
    ) {}

    /**
     * @throws ValidationException
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     */
    public function create(CreateBoxOfficeOrderDTO $data, EventDomainObject $event, ?int $occurrenceId): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($data, $event, $occurrenceId) {
            $this->seatingEventLock->lock($event->getId());

            $productOrderDetails = $this->cartValidationService->validate($data, $occurrenceId);
            $discount = $this->discountFactory->fromDiscount($data->discount, $event->getId());

            $order = $this->orderManagementService->createNewOrder(
                eventId: $event->getId(),
                event: $event,
                timeOutMinutes: self::RESERVATION_MINUTES,
                locale: $data->locale,
                promoCode: null,
                sessionId: sha1(Str::uuid().Str::random(40)),
            );

            $this->orderRepository->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::BOX_OFFICE_ID => $data->box_office->getId(),
                OrderDomainObjectAbstract::BOX_OFFICE_OPERATOR_NAME => $data->session->operator_name,
                OrderDomainObjectAbstract::FIRST_NAME => $data->buyer->first_name ?: __('Box office sale'),
                OrderDomainObjectAbstract::LAST_NAME => $data->buyer->last_name ?? '',
                OrderDomainObjectAbstract::EMAIL => $data->buyer->email,
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::AWAITING_PAYMENT->name,
            ]);

            $orderItems = $this->orderItemProcessingService->process(
                order: $order,
                productsOrderDetails: $productOrderDetails,
                event: $event,
                promoCode: $discount,
                allowClientPrices: $data->box_office->getAllowPriceOverride(),
                applyPlatformFee: false,
            );

            $order = $this->orderManagementService->updateOrderTotals(
                $order,
                $orderItems->map(fn (ProcessedOrderItemDTO $item) => $item->order_item),
            );

            $this->seatClaimService->claimForOrderItems($order, $orderItems, allowBlocked: true);

            $attendees = $this->createAttendees($order, $data);
            $this->seatClaimService->pairWithAttendees($order->getId());
            $this->createOrderQuestionAnswers($order, $data);
            $this->createAttendeeQuestionAnswers($order, $data, $attendees);
            $this->logPriceAdjustments($order, $data);

            return $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->loadRelation(AttendeeDomainObject::class)
                ->findById($order->getId());
        });
    }

    /**
     * @return Collection<AttendeeDomainObject>
     */
    private function createAttendees(OrderDomainObject $order, CreateBoxOfficeOrderDTO $data): Collection
    {
        $inserts = [];
        $seatClaims = $this->seatClaimService->claimsForOrder($order->getId())
            ->groupBy(fn (SeatClaimDomainObject $claim) => $claim->getOrderItemId())
            ->map(fn (Collection $claims) => $claims->values()->all())
            ->all();

        foreach ($order->getOrderItems() as $item) {
            if ($item->getProductType() !== ProductType::TICKET->name) {
                continue;
            }

            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $seatClaim = empty($seatClaims[$item->getId()]) ? null : array_shift($seatClaims[$item->getId()]);

                $inserts[] = [
                    AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
                    AttendeeDomainObjectAbstract::PRODUCT_ID => $item->getProductId(),
                    AttendeeDomainObjectAbstract::PRODUCT_PRICE_ID => $item->getProductPriceId(),
                    AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $item->getEventOccurrenceId(),
                    AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::AWAITING_PAYMENT->name,
                    AttendeeDomainObjectAbstract::EMAIL => $data->buyer->email,
                    AttendeeDomainObjectAbstract::FIRST_NAME => $data->buyer->first_name ?: __('Box office sale'),
                    AttendeeDomainObjectAbstract::LAST_NAME => $data->buyer->last_name ?? '',
                    AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
                    AttendeeDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ATTENDEE_PREFIX),
                    AttendeeDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::ATTENDEE_PREFIX),
                    AttendeeDomainObjectAbstract::LOCALE => $order->getLocale(),
                    AttendeeDomainObjectAbstract::SEAT_UID => $seatClaim?->getSeatUid(),
                    AttendeeDomainObjectAbstract::SEAT_LABEL => $seatClaim?->getSeatLabel(),
                ];
            }
        }

        if ($inserts === []) {
            return collect();
        }

        $this->attendeeRepository->insert($inserts);

        return $this->attendeeRepository->findWhereIn(
            field: AttendeeDomainObjectAbstract::SHORT_ID,
            values: array_column($inserts, AttendeeDomainObjectAbstract::SHORT_ID),
        );
    }

    /**
     * @param  Collection<AttendeeDomainObject>  $attendees
     */
    private function createAttendeeQuestionAnswers(OrderDomainObject $order, CreateBoxOfficeOrderDTO $data, Collection $attendees): void
    {
        if (! $data->box_office->getCollectOrderQuestions() || $data->attendees === []) {
            return;
        }

        $productQuestionIds = $this->questionRepository
            ->findWhere([
                'event_id' => $order->getEventId(),
                'belongs_to' => QuestionBelongsTo::PRODUCT->name,
            ])
            ->map(fn (QuestionDomainObject $question) => $question->getId())
            ->all();

        $unassigned = $attendees->groupBy(fn (AttendeeDomainObject $attendee) => $attendee->getProductPriceId())
            ->map(fn (Collection $group) => $group->values()->all())
            ->all();

        $slot = 0;
        foreach ($data->items as $item) {
            for ($unit = 0; $unit < $item->quantity; $unit++) {
                $entry = $data->attendees[$slot++] ?? null;
                $attendee = empty($unassigned[$item->product_price_id]) ? null : array_shift($unassigned[$item->product_price_id]);

                foreach ($entry['questions'] ?? [] as $question) {
                    $questionId = (int) ($question['question_id'] ?? 0);
                    $answer = $this->extractAnswer($question['response'] ?? null);

                    if ($answer === null || ! in_array($questionId, $productQuestionIds, true)) {
                        continue;
                    }

                    $this->questionAnswerRepository->create([
                        'question_id' => $questionId,
                        'answer' => $answer,
                        'order_id' => $order->getId(),
                        'product_id' => $item->product_id,
                        'attendee_id' => $attendee?->getId(),
                    ]);
                }
            }
        }
    }

    private function createOrderQuestionAnswers(OrderDomainObject $order, CreateBoxOfficeOrderDTO $data): void
    {
        if (! $data->box_office->getCollectOrderQuestions() || $data->questions === []) {
            return;
        }

        $orderQuestionIds = $this->questionRepository
            ->findWhere([
                'event_id' => $order->getEventId(),
                'belongs_to' => QuestionBelongsTo::ORDER->name,
            ])
            ->map(fn (QuestionDomainObject $question) => $question->getId())
            ->all();

        foreach ($data->questions as $question) {
            $questionId = (int) ($question['question_id'] ?? 0);
            $answer = $this->extractAnswer($question['response'] ?? null);

            if ($answer === null || ! in_array($questionId, $orderQuestionIds, true)) {
                continue;
            }

            $this->questionAnswerRepository->create([
                'question_id' => $questionId,
                'answer' => $answer,
                'order_id' => $order->getId(),
            ]);
        }
    }

    private function extractAnswer(mixed $response): mixed
    {
        $answer = is_array($response) && array_key_exists('answer', $response) ? $response['answer'] : $response;

        if ($answer === null || $answer === '' || $answer === []) {
            return null;
        }

        return $answer;
    }

    private function logPriceAdjustments(OrderDomainObject $order, CreateBoxOfficeOrderDTO $data): void
    {
        $overrides = $data->items
            ->filter(fn (BoxOfficeOrderItemDTO $item) => $item->override_price !== null)
            ->map(fn (BoxOfficeOrderItemDTO $item) => [
                'product_price_id' => $item->product_price_id,
                'override_price' => $item->override_price,
            ])
            ->values()
            ->all();

        if ($overrides === [] && $data->discount === null) {
            return;
        }

        $this->orderAuditLogService->logBoxOfficeAction(
            action: OrderAuditAction::BOX_OFFICE_PRICE_ADJUSTED,
            eventId: $order->getEventId(),
            orderId: $order->getId(),
            operatorName: $data->session->operator_name,
            details: array_filter([
                'overrides' => $overrides,
                'discount' => $data->discount?->toArray(),
            ]),
            ipAddress: $data->ip_address,
            userAgent: $data->user_agent,
        );
    }
}
