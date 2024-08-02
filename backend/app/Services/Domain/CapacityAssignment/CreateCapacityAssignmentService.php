<?php

namespace HiEvents\Services\Domain\CapacityAssignment;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;
use HiEvents\Services\Domain\Ticket\EventTicketValidationService;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use Illuminate\Database\DatabaseManager;

class CreateCapacityAssignmentService
{
    public function __construct(
        private readonly DatabaseManager                            $databaseManager,
        private readonly CapacityAssignmentRepositoryInterface      $capacityAssignmentRepository,
        private readonly EventTicketValidationService               $eventTicketValidationService,
        private readonly CapacityAssignmentTicketAssociationService $capacityAssignmentTicketAssociationService,
        private readonly TicketPriceRepositoryInterface             $ticketPriceRepository,
    )
    {
    }

    /**
     * @throws UnrecognizedTicketIdException
     */
    public function createCapacityAssignment(
        CapacityAssignmentDomainObject $capacityAssignment,
        array                          $ticketIds,
    ): CapacityAssignmentDomainObject
    {
        $this->eventTicketValidationService->validateTicketIds($ticketIds, $capacityAssignment->getEventId());

        return $this->persistAssignmentAndAssociateTickets($capacityAssignment, $ticketIds);
    }

    private function persistAssignmentAndAssociateTickets(
        CapacityAssignmentDomainObject $capacityAssignment,
        ?array                         $ticketIds,
    ): CapacityAssignmentDomainObject
    {
        return $this->databaseManager->transaction(function () use ($capacityAssignment, $ticketIds) {
            /** @var CapacityAssignmentDomainObject $capacityAssignment */
            $capacityAssignment = $this->capacityAssignmentRepository->create([
                CapacityAssignmentDomainObjectAbstract::NAME => $capacityAssignment->getName(),
                CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
                CapacityAssignmentDomainObjectAbstract::CAPACITY => $capacityAssignment->getCapacity(),
                CapacityAssignmentDomainObjectAbstract::APPLIES_TO => $capacityAssignment->getAppliesTo(),
                CapacityAssignmentDomainObjectAbstract::STATUS => $capacityAssignment->getStatus(),
                CapacityAssignmentDomainObjectAbstract::USED_CAPACITY => $this->getUsedCapacity($ticketIds),
            ]);

            if ($capacityAssignment->getAppliesTo() === CapacityAssignmentAppliesTo::TICKETS->name) {
                $this->capacityAssignmentTicketAssociationService->addCapacityToTickets(
                    capacityAssignmentId: $capacityAssignment->getId(),
                    ticketIds: $ticketIds,
                    removePreviousAssignments: false,
                );
            }

            return $capacityAssignment;
        });
    }

    private function getUsedCapacity(array $ticketIds): int
    {
        $ticketPrices = $this->ticketPriceRepository->findWhereIn('ticket_id', $ticketIds);

        return $ticketPrices->sum(fn(TicketPriceDomainObject $ticketPrice) => $ticketPrice->getQuantitySold());
    }
}
