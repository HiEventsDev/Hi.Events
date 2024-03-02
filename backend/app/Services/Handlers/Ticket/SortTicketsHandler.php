<?php

namespace HiEvents\Services\Handlers\Ticket;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;

readonly class SortTicketsHandler
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $eventId, array $data): void
    {
        $orderedTicketIds = collect($data)->sortBy('order')->pluck('id')->toArray();

        $ticketIdsResult = $this->ticketRepository->findWhere([
            'event_id' => $eventId,
        ])
            ->map(fn($ticket) => $ticket->getId())
            ->toArray();

        // Check if the orderedTicketIds array exactly matches the ticket IDs from the database
        $missingInOrdered = array_diff($ticketIdsResult, $orderedTicketIds);
        $extraInOrdered = array_diff($orderedTicketIds, $ticketIdsResult);

        if (!empty($missingInOrdered) || !empty($extraInOrdered)) {
            throw new ResourceConflictException(
                __('The ordered ticket IDs must exactly match all tickets for the event without missing or extra IDs.')
            );
        }

        $this->ticketRepository->sortTickets($eventId, $orderedTicketIds);
    }
}
