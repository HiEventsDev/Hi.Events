<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;

class EventTicketValidationService
{
    public function __construct(
        readonly private TicketRepositoryInterface $ticketRepository,
    )
    {
    }

    /**
     * @throws UnrecognizedTicketIdException
     */
    public function validateTicketIds(array $ticketIds, int $eventId): void
    {
        $validTicketIds = $this->ticketRepository->findWhere([
            'event_id' => $eventId,
        ])->map(fn(TicketDomainObject $ticket) => $ticket->getId())
            ->toArray();

        $invalidTicketIds = array_diff($ticketIds, $validTicketIds);

        if (!empty($invalidTicketIds)) {
            throw new UnrecognizedTicketIdException(
                __('Invalid ticket ids: :ids', ['ids' => implode(', ', $invalidTicketIds)])
            );
        }
    }
}
