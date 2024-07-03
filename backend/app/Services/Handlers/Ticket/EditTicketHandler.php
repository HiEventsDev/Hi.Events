<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Ticket;

use Exception;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Exceptions\CannotChangeTicketTypeException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Tax\DTO\TaxAndTicketAssociateParams;
use HiEvents\Services\Domain\Tax\TaxAndTicketAssociationService;
use HiEvents\Services\Domain\Ticket\TicketPriceUpdateService;
use HiEvents\Services\Handlers\Ticket\DTO\UpsertTicketDTO;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * @todo - Move logic into a domain service
 */
class EditTicketHandler
{
    public function __construct(
        private readonly TicketRepositoryInterface      $ticketRepository,
        private readonly TaxAndTicketAssociationService $taxAndTicketAssociationService,
        private readonly DatabaseManager                $databaseManager,
        private readonly TicketPriceUpdateService       $priceUpdateService,
        private readonly HTMLPurifier                   $purifier,
        private readonly EventRepositoryInterface       $eventRepository,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpsertTicketDTO $ticketsData): DomainObjectInterface
    {
        return $this->databaseManager->transaction(function () use ($ticketsData) {
            $where = [
                'event_id' => $ticketsData->event_id,
                'id' => $ticketsData->ticket_id,
            ];

            $ticket = $this->updateTicket($ticketsData, $where);

            $this->addTaxes($ticket, $ticketsData);

            $this->priceUpdateService->updatePrices(
                $ticket,
                $ticketsData,
                $ticket->getTicketPrices(),
                $this->eventRepository->findById($ticketsData->event_id)
            );

            return $this->ticketRepository
                ->loadRelation(TicketPriceDomainObject::class)
                ->findById($ticket->getId());
        });
    }

    /**
     * @throws CannotChangeTicketTypeException
     */
    private function updateTicket(UpsertTicketDTO $ticketsData, array $where): TicketDomainObject
    {
        $event = $this->eventRepository->findById($ticketsData->event_id);

        $this->validateChangeInTicketType($ticketsData);

        $this->ticketRepository->updateWhere(
            attributes: [
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
            ],
            where: $where
        );

        return $this->ticketRepository
            ->loadRelation(TicketPriceDomainObject::class)
            ->findFirstWhere($where);
    }

    /**
     * @throws Exception
     */
    private function addTaxes(TicketDomainObject $ticket, UpsertTicketDTO $ticketsData): void
    {
        $this->taxAndTicketAssociationService->addTaxesToTicket(
            new TaxAndTicketAssociateParams(
                ticketId: $ticket->getId(),
                accountId: $ticketsData->account_id,
                taxAndFeeIds: $ticketsData->tax_and_fee_ids,
            )
        );
    }

    /**
     * @throws CannotChangeTicketTypeException
     * @todo - We should probably check reserved tickets here as well
     */
    private function validateChangeInTicketType(UpsertTicketDTO $ticketsData): void
    {
        $ticket = $this->ticketRepository
            ->loadRelation(TicketPriceDomainObject::class)
            ->findById($ticketsData->ticket_id);

        $quantitySold = $ticket->getTicketPrices()
            ->sum(fn(TicketPriceDomainObject $price) => $price->getQuantitySold());

        if ($ticket->getType() !== $ticketsData->type->name && $quantitySold > 0) {
            throw new CannotChangeTicketTypeException(
                __('Ticket type cannot be changed as tickets have been registered for this type')
            );
        }
    }
}
