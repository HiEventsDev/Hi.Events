<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Ticket;

use Exception;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Tax\DTO\TaxAndTicketAssociateParams;
use HiEvents\Services\Domain\Tax\TaxAndTicketAssociationService;
use HiEvents\Services\Domain\Ticket\TicketPriceCreateService;
use HiEvents\Services\Handlers\Ticket\DTO\UpsertTicketDTO;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class CreateTicketHandler
{
    public function __construct(
        private TicketRepositoryInterface      $ticketRepository,
        private DatabaseManager                $databaseManager,
        private TaxAndTicketAssociationService $taxAndTicketAssociationService,
        private TicketPriceCreateService       $priceCreateService,
        private HTMLPurifier                   $purifier,
        private EventRepositoryInterface       $eventRepository,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertTicketDTO $ticketsData): TicketDomainObject
    {
        return $this->databaseManager->transaction(function () use ($ticketsData) {
            $ticket = $this->createTicket($ticketsData);

            if ($ticketsData->tax_and_fee_ids) {
                $ticket = $this->handleTaxes($ticket, $ticketsData);
            }

            return $this->priceCreateService->createPrices(
                ticket: $ticket,
                ticketsData: $ticketsData,
                event: $this->eventRepository->findById($ticketsData->event_id)
            );
        });
    }

    private function createTicket(UpsertTicketDTO $ticketsData): TicketDomainObject
    {
        $event = $this->eventRepository->findById($ticketsData->event_id);

        return $this->ticketRepository->create([
            'title' => $ticketsData->title,
            'type' => $ticketsData->type->name,
            'order' => $ticketsData->order,
            'sale_start_date' => $ticketsData->sale_start_date
                ? DateHelper::convertToUTC($ticketsData->sale_start_date, $event->getTimezone())
                : null,
            'sale_end_date' => $ticketsData->sale_end_date
                ? DateHelper::convertToUTC($ticketsData->sale_end_date, $event->getTimezone())
                : null,
            'max_per_order' => $ticketsData->max_per_order,
            'description' => $this->purifier->purify($ticketsData->description),
            'min_per_order' => $ticketsData->min_per_order,
            'is_hidden' => $ticketsData->is_hidden,
            'hide_before_sale_start_date' => $ticketsData->hide_before_sale_start_date,
            'hide_after_sale_end_date' => $ticketsData->hide_after_sale_end_date,
            'hide_when_sold_out' => $ticketsData->hide_when_sold_out,
            'show_quantity_remaining' => $ticketsData->show_quantity_remaining,
            'is_hidden_without_promo_code' => $ticketsData->is_hidden_without_promo_code,
            'event_id' => $ticketsData->event_id,
        ]);
    }

    /**
     * @throws Exception
     */
    private function handleTaxes(TicketDomainObject $ticket, UpsertTicketDTO $ticketsData): TicketDomainObject
    {
        return $ticket->setTaxAndFees(
            $this->taxAndTicketAssociationService->addTaxesToTicket(
                new TaxAndTicketAssociateParams(
                    ticketId: $ticket->getId(),
                    accountId: $ticketsData->account_id,
                    taxAndFeeIds: $ticketsData->tax_and_fee_ids,
                )
            )
        );
    }
}
