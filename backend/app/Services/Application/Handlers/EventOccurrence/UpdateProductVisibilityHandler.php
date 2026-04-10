<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductOccurrenceVisibilityDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpdateProductVisibilityDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class UpdateProductVisibilityHandler
{
    public function __construct(
        private readonly ProductOccurrenceVisibilityRepositoryInterface $visibilityRepository,
        private readonly ProductRepositoryInterface                     $productRepository,
        private readonly EventOccurrenceRepositoryInterface             $occurrenceRepository,
        private readonly DatabaseManager                                $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(UpdateProductVisibilityDTO $dto): Collection
    {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $dto->event_occurrence_id,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if (!$occurrence) {
            throw new ResourceNotFoundException(
                __('Occurrence :id not found for this event', ['id' => $dto->event_occurrence_id])
            );
        }

        return $this->databaseManager->transaction(function () use ($dto) {
            $this->visibilityRepository->deleteWhere([
                ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => $dto->event_occurrence_id,
            ]);

            $allProducts = $this->productRepository->findWhere([
                ProductDomainObjectAbstract::EVENT_ID => $dto->event_id,
            ]);

            $allProductIds = $allProducts->pluck('id')->sort()->values()->toArray();
            $selectedProductIds = collect($dto->product_ids)->sort()->values()->toArray();

            $invalidIds = array_diff($selectedProductIds, $allProductIds);
            if (!empty($invalidIds)) {
                throw new ResourceNotFoundException(
                    __('One or more product IDs do not belong to this event')
                );
            }

            if ($allProductIds === $selectedProductIds) {
                return collect();
            }

            foreach ($dto->product_ids as $productId) {
                $this->visibilityRepository->create([
                    ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => $dto->event_occurrence_id,
                    ProductOccurrenceVisibilityDomainObjectAbstract::PRODUCT_ID => $productId,
                ]);
            }

            return $this->visibilityRepository->findWhere([
                ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => $dto->event_occurrence_id,
            ]);
        });
    }
}
