<?php

namespace TicketKitten\Service\Common\Order;

use Illuminate\Support\Collection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\Generated\TicketDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\PromoCodeDomainObject;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Helper\Currency;
use TicketKitten\Http\DataTransferObjects\OrderTicketPriceDTO;
use TicketKitten\Http\DataTransferObjects\TicketOrderDetailsDTO;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;
use TicketKitten\Service\Common\Tax\TaxAndFeeCalculationService;
use TicketKitten\Service\Common\Ticket\TicketPriceService;

readonly class OrderItemProcessingService
{
    public function __construct(
        private OrderRepositoryInterface    $orderRepository,
        private TicketRepositoryInterface   $ticketRepository,
        private TaxAndFeeCalculationService $taxCalculationService,
        private TicketPriceService          $ticketPriceService,
    )
    {
    }

    /**
     * @param OrderDomainObject $order
     * @param Collection<TicketOrderDetailsDTO> $ticketsOrderDetails
     * @param EventDomainObject $event
     * @param PromoCodeDomainObject|null $promoCode
     * @return Collection
     */
    public function process(
        OrderDomainObject      $order,
        Collection             $ticketsOrderDetails,
        EventDomainObject      $event,
        ?PromoCodeDomainObject $promoCode
    ): Collection
    {
        $orderItems = collect();

        foreach ($ticketsOrderDetails as $ticketOrderDetail) {
            $ticket = $this->ticketRepository
                ->loadRelation(TaxAndFeesDomainObject::class)
                ->loadRelation(TicketPriceDomainObject::class)
                ->findFirstWhere([
                    TicketDomainObjectAbstract::ID => $ticketOrderDetail->ticket_id,
                    TicketDomainObjectAbstract::EVENT_ID => $event->getId(),
                ]);

            if ($ticket === null) {
                throw new ResourceNotFoundException(
                    sprintf('Ticket with id %s not found', $ticketOrderDetail->ticket_id)
                );
            }

            $ticketOrderDetail->quantities->each(function (OrderTicketPriceDTO $ticketPrice) use ($promoCode, $order, $orderItems, $ticket) {
                if ($ticketPrice->quantity === 0) {
                    return;
                }
                $orderItemData = $this->calculateOrderItemData($ticket, $ticketPrice, $order, $promoCode);
                $orderItems->push($this->orderRepository->addOrderItem($orderItemData));
            });
        }

        return $orderItems;
    }

    private function calculateOrderItemData(
        TicketDomainObject     $ticket,
        OrderTicketPriceDTO    $ticketPriceDetails,
        OrderDomainObject      $order,
        ?PromoCodeDomainObject $promoCode
    ): array
    {
        $prices = $this->ticketPriceService->getPrice($ticket, $ticketPriceDetails, $promoCode);
        $priceWithDiscount = $prices->price;
        $priceBeforeDiscount = $prices->price_before_discount;

        $itemTotalWithDiscount = $priceWithDiscount * $ticketPriceDetails->quantity;

        $taxesAndFees = $this->taxCalculationService->calculateTaxAndFeesForTicket(
            ticket: $ticket,
            price: $priceWithDiscount,
            quantity: $ticketPriceDetails->quantity
        );

        return [
            'ticket_id' => $ticket->getId(),
            'ticket_price_id' => $ticketPriceDetails->price_id,
            'quantity' => $ticketPriceDetails->quantity,
            'price_before_discount' => $priceBeforeDiscount,
            'total_before_additions' => Currency::round($itemTotalWithDiscount),
            'price' => $priceWithDiscount,
            'order_id' => $order->getId(),
            'item_name' => $this->getOrderItemLabel($ticket, $ticketPriceDetails->price_id),
            'total_tax' => $taxesAndFees->taxTotal,
            'total_service_fee' => $taxesAndFees->feeTotal,
            'total_gross' => Currency::round($itemTotalWithDiscount + $taxesAndFees->taxTotal + $taxesAndFees->feeTotal),
            'taxes_and_fees_rollup' => $taxesAndFees->rollUp,
        ];
    }

    public function getOrderItemLabel(TicketDomainObject $ticket, int $priceId): string
    {
        if ($ticket->isTieredType()) {
            return $ticket->getTitle() . ' - ' . $ticket->getTicketPrices()
                    ?->filter(fn($p) => $p->getId() === $priceId)->first()
                    ?->getLabel();
        }

        return $ticket->getTitle();
    }
}
