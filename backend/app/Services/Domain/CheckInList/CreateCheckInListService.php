<?php

namespace HiEvents\Services\Domain\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Ticket\EventTicketValidationService;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use Illuminate\Database\DatabaseManager;

class CreateCheckInListService
{
    public function __construct(
        private readonly CheckInListRepositoryInterface      $checkInListRepository,
        private readonly EventTicketValidationService        $eventTicketValidationService,
        private readonly CheckInListTicketAssociationService $checkInListTicketAssociationService,
        private readonly DatabaseManager                     $databaseManager,
        private readonly EventRepositoryInterface            $eventRepository,

    )
    {
    }

    /**
     * @throws UnrecognizedTicketIdException
     */
    public function createCheckInList(CheckInListDomainObject $checkInList, array $ticketIds): CheckInListDomainObject
    {
        return $this->databaseManager->transaction(function () use ($checkInList, $ticketIds) {
            $this->eventTicketValidationService->validateTicketIds($ticketIds, $checkInList->getEventId());
            $event = $this->eventRepository->findById($checkInList->getEventId());

            $newCheckInList = $this->checkInListRepository->create([
                CheckInListDomainObjectAbstract::NAME => $checkInList->getName(),
                CheckInListDomainObjectAbstract::DESCRIPTION => $checkInList->getDescription(),
                CheckInListDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
                CheckInListDomainObjectAbstract::EXPIRES_AT => $checkInList->getExpiresAt()
                    ? DateHelper::convertToUTC($checkInList->getExpiresAt(), $event->getTimezone())
                    : null,
                CheckInListDomainObjectAbstract::ACTIVATES_AT => $checkInList->getActivatesAt()
                    ? DateHelper::convertToUTC($checkInList->getActivatesAt(), $event->getTimezone())
                    : null,
                CheckInListDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::CHECK_IN_LIST_PREFIX),
            ]);

            $this->checkInListTicketAssociationService->addCheckInListToTickets(
                checkInListId: $newCheckInList->getId(),
                ticketIds: $ticketIds,
                removePreviousAssignments: false,
            );

            return $newCheckInList;
        });
    }
}
