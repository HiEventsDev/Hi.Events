<?php

namespace HiEvents\Services\Domain\Ticket;

use Exception;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Tax\DTO\TaxAndTicketAssociateParams;
use HiEvents\Services\Domain\Tax\TaxAndTicketAssociationService;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class CreateTicketService
{
    public function __construct(
        private readonly TicketRepositoryInterface      $ticketRepository,
        private readonly DatabaseManager                $databaseManager,
        private readonly TaxAndTicketAssociationService $taxAndTicketAssociationService,
        private readonly TicketPriceCreateService       $priceCreateService,
        private readonly HTMLPurifier                   $purifier,
        private readonly EventRepositoryInterface       $eventRepository,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function createTicket(
        TicketDomainObject $ticket,
        int                $accountId,
        ?array             $taxAndFeeIds = null,
    ): TicketDomainObject
    {
        return $this->databaseManager->transaction(function () use ($accountId, $taxAndFeeIds, $ticket) {
            $persistedTicket = $this->persistTicket($ticket);

            if ($taxAndFeeIds) {
                $this->associateTaxesAndFees($persistedTicket, $taxAndFeeIds, $accountId);
            }

            return $this->createTicketPrices($persistedTicket, $ticket);
        });
    }

    private function persistTicket(TicketDomainObject $ticketsData): TicketDomainObject
    {
        $event = $this->eventRepository->findById($ticketsData->getEventId());

        return $this->ticketRepository->create([
            'title' => $ticketsData->getTitle(),
            'type' => $ticketsData->getType(),
            'order' => $ticketsData->getOrder(),
            'sale_start_date' => $ticketsData->getSaleStartDate()
                ? DateHelper::convertToUTC($ticketsData->getSaleStartDate(), $event->getTimezone())
                : null,
            'sale_end_date' => $ticketsData->getSaleEndDate()
                ? DateHelper::convertToUTC($ticketsData->getSaleEndDate(), $event->getTimezone())
                : null,
            'max_per_order' => $ticketsData->getMaxPerOrder(),
            'description' => $this->purifier->purify($ticketsData->getDescription()),
            'min_per_order' => $ticketsData->getMinPerOrder(),
            'is_hidden' => $ticketsData->getIsHidden(),
            'hide_before_sale_start_date' => $ticketsData->getHideBeforeSaleStartDate(),
            'hide_after_sale_end_date' => $ticketsData->getHideAfterSaleEndDate(),
            'hide_when_sold_out' => $ticketsData->getHideWhenSoldOut(),
            'show_quantity_remaining' => $ticketsData->getShowQuantityRemaining(),
            'is_hidden_without_promo_code' => $ticketsData->getIsHiddenWithoutPromoCode(),
            'event_id' => $ticketsData->getEventId(),
        ]);
    }

    /**
     * @throws Exception
     */
    private function createTicketTaxesAndFees(
        TicketDomainObject $ticket,
        array              $taxAndFeeIds,
        int                $accountId,
    ): Collection
    {
        return $this->taxAndTicketAssociationService->addTaxesToTicket(
            new TaxAndTicketAssociateParams(
                ticketId: $ticket->getId(),
                accountId: $accountId,
                taxAndFeeIds: $taxAndFeeIds,
            ),
        );
    }

    /**
     * @throws Exception
     */
    private function associateTaxesAndFees(TicketDomainObject $persistedTicket, array $taxAndFeeIds, int $accountId): void
    {
        $persistedTicket->setTaxAndFees($this->createTicketTaxesAndFees(
            ticket: $persistedTicket,
            taxAndFeeIds: $taxAndFeeIds,
            accountId: $accountId,
        ));
    }

    private function createTicketPrices(TicketDomainObject $persistedTicket, TicketDomainObject $ticket): TicketDomainObject
    {
        $prices = $this->priceCreateService->createPrices(
            ticketId: $persistedTicket->getId(),
            prices: $ticket->getTicketPrices(),
            event: $this->eventRepository->findById($ticket->getEventId()),
        );

        return $persistedTicket->setTicketPrices($prices);
    }
}
