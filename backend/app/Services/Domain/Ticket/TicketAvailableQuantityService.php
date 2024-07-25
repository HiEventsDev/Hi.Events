<?php

namespace HiEvents\Services\Domain\Ticket;

use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use Illuminate\Support\Collection;

class TicketAvailableQuantityService
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepository,
        private readonly OrderRepositoryInterface  $orderRepository
    )
    {
    }

    public function getAvailableQuantity(int $ticketId, int $ticketPriceId): int
    {
        $ticketPriceAvailableQuantity = $this->ticketRepository->getQuantityRemainingForTicketPrice($ticketId, $ticketPriceId);
        $capacityAssignments = $this->ticketRepository->getCapacityAssignmentsByTicketId($ticketId);

        if ($capacityAssignments->isNotEmpty()) {
            $capacityAssignmentAvailableQuantity = $this->calculateCapacityAssignmentAvailableQuantity($capacityAssignments, $ticketId);
            $ticketPriceAvailableQuantity = min($ticketPriceAvailableQuantity, $capacityAssignmentAvailableQuantity);
        }

        return max(0, $ticketPriceAvailableQuantity);
    }

    private function calculateCapacityAssignmentAvailableQuantity(Collection $capacityAssignments, int $ticketId): int
    {
        return $capacityAssignments->map(function ($assignment) use ($ticketId) {
            $reservedQuantity = $this->orderRepository->getReservedQuantityForTicketPrice($ticketId, $assignment->getId());

            return max(0, $assignment->getCapacity() - $assignment->getUsedCapacity() - $reservedQuantity);
        })->min();
    }
}
