<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Generated\BoxOfficeDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Database\DatabaseManager;

class CreateBoxOfficeService
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly EventProductValidationService $eventProductValidationService,
        private readonly BoxOfficeProductAssociationService $productAssociationService,
        private readonly BoxOfficePinService $pinService,
        private readonly DatabaseManager $databaseManager,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @throws UnrecognizedProductIdException
     */
    public function createBoxOffice(BoxOfficeDomainObject $boxOffice, array $productIds, ?string $pin): BoxOfficeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($boxOffice, $productIds, $pin) {
            $this->eventProductValidationService->validateProductIds($productIds, $boxOffice->getEventId());
            $event = $this->eventRepository->findById($boxOffice->getEventId());

            $newBoxOffice = $this->boxOfficeRepository->create([
                BoxOfficeDomainObjectAbstract::NAME => $boxOffice->getName(),
                BoxOfficeDomainObjectAbstract::DESCRIPTION => $boxOffice->getDescription(),
                BoxOfficeDomainObjectAbstract::EVENT_ID => $boxOffice->getEventId(),
                BoxOfficeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $boxOffice->getEventOccurrenceId(),
                BoxOfficeDomainObjectAbstract::CHECK_IN_LIST_ID => $boxOffice->getCheckInListId(),
                BoxOfficeDomainObjectAbstract::PIN_HASH => $pin !== null ? $this->pinService->hash($pin) : $boxOffice->getPinHash(),
                BoxOfficeDomainObjectAbstract::ALLOW_PRICE_OVERRIDE => $boxOffice->getAllowPriceOverride(),
                BoxOfficeDomainObjectAbstract::ALLOW_DISCOUNTS => $boxOffice->getAllowDiscounts(),
                BoxOfficeDomainObjectAbstract::COLLECT_ORDER_QUESTIONS => $boxOffice->getCollectOrderQuestions(),
                BoxOfficeDomainObjectAbstract::IS_SYSTEM_DEFAULT => $boxOffice->getIsSystemDefault(),
                BoxOfficeDomainObjectAbstract::EXPIRES_AT => $boxOffice->getExpiresAt()
                    ? DateHelper::convertToUTC($boxOffice->getExpiresAt(), $event->getTimezone())
                    : null,
                BoxOfficeDomainObjectAbstract::ACTIVATES_AT => $boxOffice->getActivatesAt()
                    ? DateHelper::convertToUTC($boxOffice->getActivatesAt(), $event->getTimezone())
                    : null,
                BoxOfficeDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::BOX_OFFICE_PREFIX),
            ]);

            $this->productAssociationService->addBoxOfficeToProducts(
                boxOfficeId: $newBoxOffice->getId(),
                productIds: $productIds,
                removePreviousAssignments: false,
            );

            return $newBoxOffice;
        });
    }
}
