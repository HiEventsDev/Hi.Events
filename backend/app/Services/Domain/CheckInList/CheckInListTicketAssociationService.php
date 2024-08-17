<?php

namespace HiEvents\Services\Domain\CheckInList;

use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class CheckInListTicketAssociationService
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepository,
        public readonly DatabaseManager            $databaseManager,
    )
    {
    }

    public function addCheckInListToTickets(
        int    $checkInListId,
        ?array $ticketIds,
        bool   $removePreviousAssignments = true
    ): void
    {
        $this->databaseManager->transaction(function () use ($checkInListId, $ticketIds, $removePreviousAssignments) {
            $this->associateTicketsWithCheckInList(
                checkInListId: $checkInListId,
                ticketIds: $ticketIds,
                removePreviousAssignments: $removePreviousAssignments,
            );
        });
    }

    private function associateTicketsWithCheckInList(
        int    $checkInListId,
        ?array $ticketIds,
        bool   $removePreviousAssignments = true
    ): void
    {
        if (empty($ticketIds)) {
            return;
        }

        if ($removePreviousAssignments) {
            $this->ticketRepository->removeCheckInListFromTickets(
                checkInListId: $checkInListId,
            );
        }

        $this->ticketRepository->addCheckInListToTickets(
            checkInListId: $checkInListId,
            ticketIds: array_unique($ticketIds),
        );
    }
}
