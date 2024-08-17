<?php

namespace HiEvents\Services\Domain\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Ticket\EventTicketValidationService;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use Illuminate\Database\DatabaseManager;

class UpdateCheckInListService
{
    public function __construct(
        private readonly DatabaseManager                     $databaseManager,
        private readonly EventTicketValidationService        $eventTicketValidationService,
        private readonly CheckInListTicketAssociationService $checkInListTicketAssociationService,
        private readonly CheckInListRepositoryInterface      $checkInListRepository,
        private readonly EventRepositoryInterface            $eventRepository,
    )
    {
    }

    /**
     * @throws UnrecognizedTicketIdException
     */
    public function updateCheckInList(CheckInListDomainObject $checkInList, array $ticketIds): CheckInListDomainObject
    {
        return $this->databaseManager->transaction(function () use ($checkInList, $ticketIds) {
            $this->eventTicketValidationService->validateTicketIds($ticketIds, $checkInList->getEventId());
            $event = $this->eventRepository->findById($checkInList->getEventId());

            $this->checkInListRepository->updateWhere(
                attributes: [
                    CheckInListDomainObjectAbstract::NAME => $checkInList->getName(),
                    CheckInListDomainObjectAbstract::DESCRIPTION => $checkInList->getDescription(),
                    CheckInListDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
                    CheckInListDomainObjectAbstract::EXPIRES_AT => $checkInList->getExpiresAt()
                        ? DateHelper::convertToUTC($checkInList->getExpiresAt(), $event->getTimezone())
                        : null,
                    CheckInListDomainObjectAbstract::ACTIVATES_AT => $checkInList->getActivatesAt()
                        ? DateHelper::convertToUTC($checkInList->getActivatesAt(), $event->getTimezone())
                        : null,
                ],
                where: [
                    CheckInListDomainObjectAbstract::ID => $checkInList->getId(),
                    CheckInListDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
                ]
            );

            $this->checkInListTicketAssociationService->addCheckInListToTickets(
                checkInListId: $checkInList->getId(),
                ticketIds: $ticketIds,
            );

            return $this->checkInListRepository->findFirstWhere(
                where: [
                    CheckInListDomainObjectAbstract::ID => $checkInList->getId(),
                    CheckInListDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
                ]
            );
        });
    }
}
