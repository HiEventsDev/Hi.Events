<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\Generated\CashlessSalesPointDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\CashlessSalesPointRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\UpsertCashlessSalesPointDTO;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Hashing\HashManager;
use Throwable;

class CashlessSalesPointService
{
    public function __construct(
        private readonly CashlessSalesPointRepositoryInterface $salesPointRepository,
        private readonly EventProductValidationService $eventProductValidationService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly HashManager $hashManager,
    ) {}

    /**
     * @throws UnrecognizedProductIdException
     * @throws Throwable
     */
    public function create(UpsertCashlessSalesPointDTO $salesPointData): CashlessSalesPointDomainObject
    {
        return $this->databaseManager->transaction(function () use ($salesPointData) {
            $this->eventProductValidationService->validateProductIds(
                $salesPointData->product_ids,
                $salesPointData->event_id,
            );

            $salesPoint = $this->salesPointRepository->create([
                ...$this->commonAttributes($salesPointData),
                CashlessSalesPointDomainObjectAbstract::EVENT_ID => $salesPointData->event_id,
                CashlessSalesPointDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::CASHLESS_SALES_POINT_PREFIX),
                CashlessSalesPointDomainObjectAbstract::ACCESS_PIN => $salesPointData->access_pin
                    ? $this->hashManager->make($salesPointData->access_pin)
                    : null,
            ]);

            $this->salesPointRepository->syncProducts($salesPoint->getId(), $salesPointData->product_ids);

            return $this->reload($salesPoint->getId());
        });
    }

    /**
     * @throws UnrecognizedProductIdException
     * @throws Throwable
     */
    public function update(
        CashlessSalesPointDomainObject $existing,
        UpsertCashlessSalesPointDTO $salesPointData,
    ): CashlessSalesPointDomainObject {
        return $this->databaseManager->transaction(function () use ($existing, $salesPointData) {
            $this->eventProductValidationService->validateProductIds(
                $salesPointData->product_ids,
                $salesPointData->event_id,
            );

            $attributes = $this->commonAttributes($salesPointData);

            if ($salesPointData->access_pin !== null) {
                $attributes[CashlessSalesPointDomainObjectAbstract::ACCESS_PIN] = $this->hashManager->make($salesPointData->access_pin);
            }

            $this->salesPointRepository->updateFromArray($existing->getId(), $attributes);

            $this->salesPointRepository->syncProducts($existing->getId(), $salesPointData->product_ids);

            return $this->reload($existing->getId());
        });
    }

    private function reload(int $salesPointId): CashlessSalesPointDomainObject
    {
        return $this->salesPointRepository
            ->loadRelation(ProductDomainObject::class)
            ->findById($salesPointId);
    }

    private function commonAttributes(UpsertCashlessSalesPointDTO $salesPointData): array
    {
        $timezone = $this->eventRepository->findById($salesPointData->event_id)->getTimezone();

        return [
            CashlessSalesPointDomainObjectAbstract::NAME => $salesPointData->name,
            CashlessSalesPointDomainObjectAbstract::DESCRIPTION => $salesPointData->description,
            CashlessSalesPointDomainObjectAbstract::ALLOW_STAFF_TOPUPS => $salesPointData->allow_staff_topups,
            CashlessSalesPointDomainObjectAbstract::ACTIVATES_AT => $salesPointData->activates_at
                ? DateHelper::convertToUTC($salesPointData->activates_at, $timezone)
                : null,
            CashlessSalesPointDomainObjectAbstract::EXPIRES_AT => $salesPointData->expires_at
                ? DateHelper::convertToUTC($salesPointData->expires_at, $timezone)
                : null,
        ];
    }
}
