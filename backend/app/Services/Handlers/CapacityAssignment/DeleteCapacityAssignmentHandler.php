<?php

namespace HiEvents\Services\Handlers\CapacityAssignment;

use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class DeleteCapacityAssignmentHandler
{
    public function __construct(
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
        private readonly TicketRepositoryInterface             $ticketRepository,
        private readonly DatabaseManager                       $databaseManager,
    )
    {
    }

    public function handle(int $id, int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($id, $eventId) {
            $this->ticketRepository->removeCapacityAssignmentFromTickets(
                capacityAssignmentId: $id,
            );

            $this->capacityAssignmentRepository->deleteWhere([
                'id' => $id,
                'event_id' => $eventId,
            ]);
        });
    }
}
