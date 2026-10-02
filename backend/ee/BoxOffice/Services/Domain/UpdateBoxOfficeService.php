<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Generated\BoxOfficeDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Database\DatabaseManager;

class UpdateBoxOfficeService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly EventProductValidationService $eventProductValidationService,
        private readonly BoxOfficeProductAssociationService $productAssociationService,
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @throws UnrecognizedProductIdException
     * @throws ResourceNotFoundException
     */
    public function updateBoxOffice(BoxOfficeDomainObject $boxOffice, array $productIds): BoxOfficeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($boxOffice, $productIds) {
            $where = [
                BoxOfficeDomainObjectAbstract::ID => $boxOffice->getId(),
                BoxOfficeDomainObjectAbstract::EVENT_ID => $boxOffice->getEventId(),
            ];

            $existing = $this->boxOfficeRepository->findFirstWhere($where);

            if ($existing === null) {
                throw new ResourceNotFoundException(__('Box office not found'));
            }

            $this->eventProductValidationService->validateProductIds($productIds, $boxOffice->getEventId());
            $event = $this->eventRepository->findById($boxOffice->getEventId());

            $attributes = [
                BoxOfficeDomainObjectAbstract::NAME => $boxOffice->getName(),
                BoxOfficeDomainObjectAbstract::DESCRIPTION => $boxOffice->getDescription(),
                BoxOfficeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $boxOffice->getEventOccurrenceId(),
                BoxOfficeDomainObjectAbstract::CHECK_IN_LIST_ID => $boxOffice->getCheckInListId(),
                BoxOfficeDomainObjectAbstract::ALLOW_PRICE_OVERRIDE => $boxOffice->getAllowPriceOverride(),
                BoxOfficeDomainObjectAbstract::ALLOW_DISCOUNTS => $boxOffice->getAllowDiscounts(),
                BoxOfficeDomainObjectAbstract::COLLECT_ORDER_QUESTIONS => $boxOffice->getCollectOrderQuestions(),
                BoxOfficeDomainObjectAbstract::EXPIRES_AT => $boxOffice->getExpiresAt()
                    ? DateHelper::convertToUTC($boxOffice->getExpiresAt(), $event->getTimezone())
                    : null,
                BoxOfficeDomainObjectAbstract::ACTIVATES_AT => $boxOffice->getActivatesAt()
                    ? DateHelper::convertToUTC($boxOffice->getActivatesAt(), $event->getTimezone())
                    : null,
            ];

            $this->boxOfficeRepository->updateWhere(attributes: $attributes, where: $where);

            $this->productAssociationService->addBoxOfficeToProducts(
                boxOfficeId: $boxOffice->getId(),
                productIds: $productIds,
            );

            return $this->boxOfficeRepository->findFirstWhere($where);
        });
    }
}
