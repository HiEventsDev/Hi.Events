<?php

namespace HiEvents\Services\Domain\CapacityAssignment;

use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class CapacityAssignmentTicketAssociationService
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepository,
        public readonly DatabaseManager            $databaseManager,
    )
    {
    }

    public function addCapacityToTickets(
        int    $capacityAssignmentId,
        ?array $ticketIds,
        bool   $removePreviousAssignments = true
    ): void
    {
        $this->databaseManager->transaction(function () use ($capacityAssignmentId, $ticketIds, $removePreviousAssignments) {
            $this->associateTicketsWithCapacityAssignment(
                capacityAssignmentId: $capacityAssignmentId,
                ticketIds: $ticketIds,
                removePreviousAssignments: $removePreviousAssignments,
            );
        });
    }

    private function associateTicketsWithCapacityAssignment(
        int    $capacityAssignmentId,
        ?array $ticketIds,
        bool   $removePreviousAssignments = true
    ): void
    {
        if (empty($ticketIds)) {
            return;
        }

        if ($removePreviousAssignments) {
            $this->ticketRepository->removeCapacityAssignmentFromTickets(
                capacityAssignmentId: $capacityAssignmentId,
            );
        }

        $this->ticketRepository->addCapacityAssignmentToTickets(
            capacityAssignmentId: $capacityAssignmentId,
            ticketIds: array_unique($ticketIds),
        );
    }
}
