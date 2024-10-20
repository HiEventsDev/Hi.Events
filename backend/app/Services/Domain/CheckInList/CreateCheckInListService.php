<?php

namespace HiEvents\Services\Domain\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Database\DatabaseManager;

class CreateCheckInListService
{
    public function __construct(
        private readonly CheckInListRepositoryInterface      $checkInListRepository,
        private readonly EventProductValidationService       $eventProductValidationService,
        private readonly CheckInListProductAssociationService $checkInListProductAssociationService,
        private readonly DatabaseManager                     $databaseManager,
        private readonly EventRepositoryInterface            $eventRepository,

    )
    {
    }

    /**
     * @throws UnrecognizedProductIdException
     */
    public function createCheckInList(CheckInListDomainObject $checkInList, array $productIds): CheckInListDomainObject
    {
        return $this->databaseManager->transaction(function () use ($checkInList, $productIds) {
            $this->eventProductValidationService->validateProductIds($productIds, $checkInList->getEventId());
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

            $this->checkInListProductAssociationService->addCheckInListToProducts(
                checkInListId: $newCheckInList->getId(),
                productIds: $productIds,
                removePreviousAssignments: false,
            );

            return $newCheckInList;
        });
    }
}
