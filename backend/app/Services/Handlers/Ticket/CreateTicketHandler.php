<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Ticket;

use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\Generated\TicketPriceDomainObjectAbstract;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Services\Domain\Ticket\DTO\TicketPriceDTO;
use HiEvents\Services\Domain\Ticket\CreateTicketService;
use HiEvents\Services\Handlers\Ticket\DTO\UpsertTicketDTO;
use Throwable;

class CreateTicketHandler
{
    public function __construct(
        private readonly CreateTicketService $ticketCreateService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertTicketDTO $ticketsData): TicketDomainObject
    {
        $ticketPrices = $ticketsData->prices->map(fn(TicketPriceDTO $price) => TicketPriceDomainObject::hydrateFromArray([
            TicketPriceDomainObjectAbstract::PRICE => $ticketsData->type === TicketType::FREE ? 0.00 : $price->price,
            TicketPriceDomainObjectAbstract::LABEL => $price->label,
            TicketPriceDomainObjectAbstract::SALE_START_DATE => $price->sale_start_date,
            TicketPriceDomainObjectAbstract::SALE_END_DATE => $price->sale_end_date,
            TicketPriceDomainObjectAbstract::INITIAL_QUANTITY_AVAILABLE => $price->initial_quantity_available,
            TicketPriceDomainObjectAbstract::IS_HIDDEN => $price->is_hidden,
        ]));

        return $this->ticketCreateService->createTicket(
            ticket: (new TicketDomainObject())
                ->setTitle($ticketsData->title)
                ->setType($ticketsData->type->name)
                ->setOrder($ticketsData->order)
                ->setSaleStartDate($ticketsData->sale_start_date)
                ->setSaleEndDate($ticketsData->sale_end_date)
                ->setMaxPerOrder($ticketsData->max_per_order)
                ->setDescription($ticketsData->description)
                ->setMinPerOrder($ticketsData->min_per_order)
                ->setIsHidden($ticketsData->is_hidden)
                ->setHideBeforeSaleStartDate($ticketsData->hide_before_sale_start_date)
                ->setHideAfterSaleEndDate($ticketsData->hide_after_sale_end_date)
                ->setHideWhenSoldOut($ticketsData->hide_when_sold_out)
                ->setShowQuantityRemaining($ticketsData->show_quantity_remaining)
                ->setIsHiddenWithoutPromoCode($ticketsData->is_hidden_without_promo_code)
                ->setTicketPrices($ticketPrices)
                ->setEventId($ticketsData->event_id),
            accountId: $ticketsData->account_id,
            taxAndFeeIds: $ticketsData->tax_and_fee_ids,
        );
    }
}
