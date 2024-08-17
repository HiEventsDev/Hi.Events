<?php

namespace HiEvents\Services\Domain\CapacityAssignment;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Generated\CapacityAssignmentDomainObjectAbstract;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Services\Domain\Ticket\EventTicketValidationService;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use Illuminate\Database\DatabaseManager;

class UpdateCapacityAssignmentService
{
    public function __construct(
        private readonly DatabaseManager                            $databaseManager,
        private readonly CapacityAssignmentRepositoryInterface      $capacityAssignmentRepository,
        private readonly EventTicketValidationService               $eventTicketValidationService,
        private readonly CapacityAssignmentTicketAssociationService $capacityAssignmentTicketAssociationService,
    )
    {
    }

    /**
     * @throws UnrecognizedTicketIdException
     */
    public function updateCapacityAssignment(
        CapacityAssignmentDomainObject $capacityAssignment,
        ?array                         $ticketIds = null,
    ): CapacityAssignmentDomainObject
    {
        if ($ticketIds !== null) {
            $this->eventTicketValidationService->validateTicketIds($ticketIds, $capacityAssignment->getEventId());
        }

        return $this->updateAssignmentAndAssociateTickets($capacityAssignment, $ticketIds);
    }

    private function updateAssignmentAndAssociateTickets(
        CapacityAssignmentDomainObject $capacityAssignment,
        ?array                         $ticketIds
    ): CapacityAssignmentDomainObject
    {
        return $this->databaseManager->transaction(function () use ($capacityAssignment, $ticketIds) {
            /** @var CapacityAssignmentDomainObject $capacityAssignment */
            $this->capacityAssignmentRepository->updateWhere(
                attributes: [
                    CapacityAssignmentDomainObjectAbstract::NAME => $capacityAssignment->getName(),
                    CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
                    CapacityAssignmentDomainObjectAbstract::CAPACITY => $capacityAssignment->getCapacity(),
                    CapacityAssignmentDomainObjectAbstract::APPLIES_TO => $capacityAssignment->getAppliesTo(),
                    CapacityAssignmentDomainObjectAbstract::STATUS => $capacityAssignment->getStatus(),
                ],
                where: [
                    CapacityAssignmentDomainObjectAbstract::ID => $capacityAssignment->getId(),
                    CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
                ]
            );

            if ($capacityAssignment->getAppliesTo() === CapacityAssignmentAppliesTo::TICKETS->name) {
                $this->capacityAssignmentTicketAssociationService->addCapacityToTickets(
                    capacityAssignmentId: $capacityAssignment->getId(),
                    ticketIds: $ticketIds,
                );
            }

            return $this->capacityAssignmentRepository->findFirstWhere([
                CapacityAssignmentDomainObjectAbstract::ID => $capacityAssignment->getId(),
                CapacityAssignmentDomainObjectAbstract::EVENT_ID => $capacityAssignment->getEventId(),
            ]);
        });
    }
}
