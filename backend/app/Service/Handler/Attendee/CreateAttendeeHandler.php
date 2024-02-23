<?php

namespace TicketKitten\Service\Handler\Attendee;

use Brick\Money\Money;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\OrderDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\TicketDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\DomainObjects\Status\AttendeeStatus;
use TicketKitten\DomainObjects\Status\OrderPaymentStatus;
use TicketKitten\DomainObjects\Status\OrderStatus;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Events\OrderStatusChangedEvent;
use TicketKitten\Exceptions\InvalidTicketPriceId;
use TicketKitten\Exceptions\NoTicketsAvailableException;
use TicketKitten\Helper\IdHelper;
use TicketKitten\Http\DataTransferObjects\CreateAttendeeDTO;
use TicketKitten\Http\DataTransferObjects\CreateAttendeeTaxAndFeeDTO;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;
use TicketKitten\Service\Common\Order\OrderManagementService;
use TicketKitten\Service\Common\Tax\TaxAndFeeRollupService;
use TicketKitten\Service\Common\Ticket\TicketQuantityService;

readonly class CreateAttendeeHandler
{
    public function __construct(
        private AttendeeRepositoryInterface  $attendeeRepository,
        private OrderRepositoryInterface     $orderRepository,
        private TicketRepositoryInterface    $ticketRepository,
        private EventRepositoryInterface     $eventRepository,
        private TicketQuantityService        $ticketQuantityAdjustmentService,
        private DatabaseManager              $databaseManager,
        private TaxAndFeeRepositoryInterface $taxAndFeeRepository,
        private TaxAndFeeRollupService       $taxAndFeeRollupService,
        private OrderManagementService       $orderManagementService,
    )
    {
    }

    /**
     * @throws NoTicketsAvailableException
     * @throws Throwable
     */
    public function handle(CreateAttendeeDTO $attendeeDTO): AttendeeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($attendeeDTO) {
            $this->calculateTaxesAndFees($attendeeDTO);

            $order = $this->createOrder($attendeeDTO->event_id, $attendeeDTO);

            /** @var TicketDomainObject $ticket */
            $ticket = $this->ticketRepository
                ->loadRelation(TicketPriceDomainObject::class)
                ->findFirstWhere([
                    TicketDomainObjectAbstract::ID => $attendeeDTO->ticket_id,
                    TicketDomainObjectAbstract::EVENT_ID => $attendeeDTO->event_id,
                ]);

            $availableQuantity = $this->ticketRepository->getQuantityRemainingForTicketPrice(
                $attendeeDTO->ticket_id,
                $attendeeDTO->ticket_price_id,
            );

            if ($availableQuantity <= 0) {
                throw new NoTicketsAvailableException(__('There are no tickets available. ' .
                    'If you would like to assign a ticket to this attendee,' .
                    ' please adjust the ticket\'s available quantity.'));
            }

            $ticketPriceId = $this->getTicketPriceId($attendeeDTO, $ticket);

            $this->processTaxesAndFees($attendeeDTO);

            $orderItem = $this->createOrderItem($attendeeDTO, $order, $ticket, $ticketPriceId);

            $attendee = $this->createAttendee($order, $attendeeDTO);

            $this->orderManagementService->updateOrderTotals($order, collect([$orderItem]));

            $this->fireEventsAndUpdateQuantities($attendeeDTO, $order);

            return $attendee;
        });
    }

    private function createOrder(int $eventId, CreateAttendeeDTO $attendeeDTO): OrderDomainObject
    {
        $event = $this->eventRepository->findById($eventId);
        $publicId = Str::upper(Str::random(5));
        $total = Money::of($attendeeDTO->amount_paid, $event->getCurrency());

        return $this->orderRepository->create(
            [
                OrderDomainObjectAbstract::TOTAL_GROSS => $total->getAmount()->toFloat(),
                OrderDomainObjectAbstract::FIRST_NAME => $attendeeDTO->first_name,
                OrderDomainObjectAbstract::LAST_NAME => $attendeeDTO->last_name,
                OrderDomainObjectAbstract::EMAIL => $attendeeDTO->email,
                OrderDomainObjectAbstract::EVENT_ID => $eventId,
                OrderDomainObjectAbstract::SHORT_ID => IdHelper::randomPrefixedId(IdHelper::ORDER_PREFIX),
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_STATUS => $total->isZero()
                    ? OrderPaymentStatus::NO_PAYMENT_REQUIRED->name
                    : OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::CURRENCY => $event->getCurrency(),
                OrderDomainObjectAbstract::PUBLIC_ID => $publicId,
                OrderDomainObjectAbstract::IS_MANUALLY_CREATED => true,
            ]
        );
    }

    /**
     * @throws InvalidTicketPriceId
     */
    private function getTicketPriceId(CreateAttendeeDTO $attendeeDTO, TicketDomainObject $ticket): int
    {
        $priceIds = $ticket->getTicketPrices()->map(fn(TicketPriceDomainObject $ticketPrice) => $ticketPrice->getId());

        if ($attendeeDTO->ticket_price_id) {
            if (!$priceIds->contains($attendeeDTO->ticket_price_id)) {
                throw new InvalidTicketPriceId(__('The ticket price ID is invalid.'));
            }
            return $attendeeDTO->ticket_price_id;
        }

        /** @var TicketPriceDomainObject $ticketPrice */
        $ticketPrice = $ticket->getTicketPrices()->first();

        if ($ticketPrice) {
            return $ticketPrice->getId();
        }

        throw new InvalidTicketPriceId(__('The ticket price ID is invalid.'));
    }

    private function calculateTaxesAndFees(CreateAttendeeDTO $attendeeDTO): ?Collection
    {
        if (!$attendeeDTO->taxes_and_fees) {
            return null;
        }

        $taxesAndFees = $this->taxAndFeeRepository->findWhereIn(
            'id',
            $attendeeDTO
                ->taxes_and_fees
                ->map(fn(CreateAttendeeTaxAndFeeDTO $taxAndFee) => $taxAndFee->tax_or_fee_id)
                ->toArray()
        );

        $validatedTaxesAndFees = collect();
        $attendeeDTO->taxes_and_fees->each(function (CreateAttendeeTaxAndFeeDTO $taxAndFee) use ($validatedTaxesAndFees, $taxesAndFees) {
            $taxOrFee = $taxesAndFees->first(fn($taxOrFee) => $taxOrFee->getId() === $taxAndFee->tax_or_fee_id);

            if (!$taxOrFee) {
                throw new \RuntimeException('Tax or fee not found.');
            }

            $validatedTaxesAndFees->push($taxOrFee);
        });

        return $validatedTaxesAndFees;
    }

    private function processTaxesAndFees(CreateAttendeeDTO $attendeeDTO): void
    {
        $this->calculateTaxesAndFees($attendeeDTO)
            ?->each(fn($taxOrFee) => $this->taxAndFeeRollupService
                ->addToRollUp(
                    $taxOrFee,
                    $attendeeDTO
                        ->taxes_and_fees
                        ->first(fn($taxOrFeeDTO) => $taxOrFeeDTO->tax_or_fee_id === $taxOrFee->getId())
                        ->amount)
            );
    }

    private function createOrderItem(CreateAttendeeDTO $attendeeDTO, OrderDomainObject $order, TicketDomainObject $ticket, int $ticketPriceId): OrderItemDomainObject
    {
        return $this->orderRepository->addOrderItem(
            [
                OrderItemDomainObjectAbstract::TICKET_ID => $attendeeDTO->ticket_id,
                OrderItemDomainObjectAbstract::QUANTITY => 1,
                OrderItemDomainObjectAbstract::TOTAL_BEFORE_ADDITIONS => $attendeeDTO->amount_paid,
                OrderItemDomainObjectAbstract::TOTAL_GROSS => $attendeeDTO->amount_paid + $this->taxAndFeeRollupService->getTotalTaxesAndFees(),
                OrderItemDomainObjectAbstract::TOTAL_TAX => $this->taxAndFeeRollupService->getTotalTaxes(),
                OrderItemDomainObjectAbstract::TOTAL_SERVICE_FEE => $this->taxAndFeeRollupService->getTotalFees(),
                OrderItemDomainObjectAbstract::PRICE => $attendeeDTO->amount_paid,
                OrderItemDomainObjectAbstract::ORDER_ID => $order->getId(),
                OrderItemDomainObjectAbstract::ITEM_NAME => $ticket->getTitle(),
                OrderItemDomainObjectAbstract::TICKET_PRICE_ID => $ticketPriceId,
                OrderItemDomainObjectAbstract::TAXES_AND_FEES_ROLLUP => $this->taxAndFeeRollupService->getRollUp(),
            ]
        );
    }

    private function createAttendee(OrderDomainObject $order, CreateAttendeeDTO $attendeeDTO): AttendeeDomainObject
    {
        return $this->attendeeRepository->create([
            AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
            AttendeeDomainObjectAbstract::TICKET_ID => $attendeeDTO->ticket_id,
            AttendeeDomainObjectAbstract::TICKET_PRICE_ID => $attendeeDTO->ticket_price_id,
            AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::ACTIVE->name,
            AttendeeDomainObjectAbstract::EMAIL => $attendeeDTO->email,
            AttendeeDomainObjectAbstract::FIRST_NAME => $attendeeDTO->first_name,
            AttendeeDomainObjectAbstract::LAST_NAME => $attendeeDTO->last_name,
            AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
            AttendeeDomainObjectAbstract::PUBLIC_ID => $order->getPublicId() . '-1',
            AttendeeDomainObjectAbstract::SHORT_ID => IdHelper::randomPrefixedId(IdHelper::ATTENDEE_PREFIX),
        ]);
    }

    private function fireEventsAndUpdateQuantities(CreateAttendeeDTO $attendeeDTO, OrderDomainObject $order): void
    {
        $this->ticketQuantityAdjustmentService->increaseTicketPriceQuantitySold(
            priceId: $attendeeDTO->ticket_price_id,
        );

        event(new OrderStatusChangedEvent(
            order: $order,
            sendEmails: $attendeeDTO->send_confirmation_email,
        ));
    }
}
