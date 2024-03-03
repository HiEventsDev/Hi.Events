<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Services\Domain\Ticket\DTO\OrderTicketPriceDTO;
use HiEvents\Services\Domain\Ticket\DTO\PriceDTO;

class TicketPriceService
{
    public function getIndividualPrice(
        TicketDomainObject      $ticket,
        TicketPriceDomainObject $price,
        ?PromoCodeDomainObject  $promoCode
    ): float
    {
        return $this->getPrice($ticket, new OrderTicketPriceDTO(
            quantity: 1,
            price_id: $price->getId(),
        ), $promoCode)->price;
    }

    public function getPrice(
        TicketDomainObject     $ticket,
        OrderTicketPriceDTO    $ticketOrderDetail,
        ?PromoCodeDomainObject $promoCode
    ): PriceDTO
    {
        $price = $this->determineTicketPrice($ticket, $ticketOrderDetail);

        if ($ticket->getType() === TicketType::FREE->name) {
            return new PriceDTO(0.00);
        }

        if ($ticket->getType() === TicketType::DONATION->name) {
            return new PriceDTO($price);
        }

        if (!$promoCode || !$promoCode->appliesToTicket($ticket)) {
            return new PriceDTO($price);
        }

        if ($promoCode->getDiscountType() === PromoCodeDiscountTypeEnum::NONE->name) {
            return new PriceDTO($price);
        }

        if ($promoCode->isFixedDiscount()) {
            $discountPrice = Currency::round($price - $promoCode->getDiscount());
        } elseif ($promoCode->isPercentageDiscount()) {
            $discountPrice = Currency::round(
                $price - ($price * ($promoCode->getDiscount() / 100))
            );
        } else {
            $discountPrice = $price;
        }

        return new PriceDTO(
            price: max(0, $discountPrice),
            price_before_discount: $price
        );
    }

    private function determineTicketPrice(TicketDomainObject $ticket, OrderTicketPriceDTO $ticketOrderDetails): float
    {
        return match ($ticket->getType()) {
            TicketType::DONATION->name => max($ticket->getPrice(), $ticketOrderDetails->price),
            TicketType::PAID->name => $ticket->getPrice(),
            TicketType::FREE->name => 0.00,
            TicketType::TIERED->name => $ticket->getPriceById($ticketOrderDetails->price_id)?->getPrice()
        };
    }
}
