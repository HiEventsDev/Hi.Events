<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\TicketResale\DTO\ResaleRefundInputDTO;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleContextDTO;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleEstimateDTO;

class TicketResaleEstimateService
{
    public function __construct(
        private readonly ResaleRefundCalculator $refundCalculator,
        private readonly OrderApplicationFeeCalculationService $applicationFeeCalculationService,
    ) {}

    public function estimate(TicketResaleContextDTO $context): TicketResaleEstimateDTO
    {
        $breakdown = $this->refundCalculator->calculate(new ResaleRefundInputDTO(
            seller_unit_gross: $context->seller_unit_gross,
            replacement_unit_gross: $context->current_unit_gross,
            replacement_order_gross: $context->current_unit_gross,
            replacement_order_costs: $this->applicationFeeOnReplacementOrder($context) + $this->sellerCardFeeShare($context),
            replacement_paid_ticket_count: 1,
            seller_order_unrefunded_balance: $context->seller_order_unrefunded_balance,
        ));

        return new TicketResaleEstimateDTO(
            currency: $context->order->getCurrency(),
            amount_paid: $context->seller_unit_gross,
            estimated_fees: $breakdown->cost_share,
            estimated_refund: $breakdown->refund,
            add_ons: $this->addOnNames($context->order),
        );
    }

    private function applicationFeeOnReplacementOrder(TicketResaleContextDTO $context): int
    {
        $organizer = $context->event->getOrganizer();
        $configuration = $organizer?->getOrganizerConfiguration();

        if ($configuration === null || $configuration->getBypassApplicationFees() || $context->current_unit_gross <= 0) {
            return 0;
        }

        $currency = $context->order->getCurrency();

        $replacementOrder = (new OrderDomainObject)
            ->setCurrency($currency)
            ->setTotalGross(Currency::fromMinorUnits($context->current_unit_gross, $currency))
            ->setOrderItems(collect([
                (new OrderItemDomainObject)->setPrice($context->current_unit_price)->setQuantity(1),
            ]));

        return $this->applicationFeeCalculationService
            ->calculateApplicationFee($configuration, $replacementOrder, $organizer->getOrganizerVatSetting())
            ?->grossApplicationFee
            ->toMinorUnit() ?? 0;
    }

    private function sellerCardFeeShare(TicketResaleContextDTO $context): int
    {
        if ($context->seller_order_gross <= 0) {
            return 0;
        }

        return (int) ceil($context->seller_order_card_fee * $context->seller_unit_gross / $context->seller_order_gross);
    }

    /**
     * @return string[]
     */
    private function addOnNames(OrderDomainObject $order): array
    {
        return ($order->getOrderItems() ?? collect())
            ->filter(fn (OrderItemDomainObject $item) => $item->getProductType() !== ProductType::TICKET->name)
            ->map(fn (OrderItemDomainObject $item) => (string) $item->getItemName())
            ->unique()
            ->values()
            ->all();
    }
}
