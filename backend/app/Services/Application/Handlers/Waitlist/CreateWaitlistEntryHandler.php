<?php

namespace HiEvents\Services\Application\Handlers\Waitlist;

use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\CreateWaitlistEntryDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Waitlist\CreateWaitlistEntryService;
use Illuminate\Validation\ValidationException;

class CreateWaitlistEntryHandler
{
    public function __construct(
        private readonly CreateWaitlistEntryService       $createWaitlistEntryService,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly EventRepositoryInterface         $eventRepository,
        private readonly ProductPriceRepositoryInterface  $productPriceRepository,
        private readonly ProductRepositoryInterface       $productRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly OccurrencePurchaseEligibilityService $occurrenceEligibilityService,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     */
    public function handle(CreateWaitlistEntryDTO $dto): WaitlistEntryDomainObject
    {
        $event = $this->eventRepository->findById($dto->event_id);
        if ($event !== null && $event->isRecurring() && $dto->event_occurrence_id === null) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('An event date must be selected.'),
            ]);
        }

        if ($event !== null
            && $event->getType() === EventType::SINGLE->name
            && $dto->event_occurrence_id === null
        ) {
            $occurrence = $this->occurrenceRepository
                ->findWhere(
                    where: [
                        EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
                    ],
                    orderAndDirections: [
                        new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, 'asc'),
                    ],
                )
                ->first();

            if ($occurrence !== null) {
                $dto = CreateWaitlistEntryDTO::fromArray(array_merge(
                    $dto->toArray(),
                    ['event_occurrence_id' => $occurrence->getId()],
                ));
            }
        }

        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $dto->event_id,
        ]);

        $productPrice = $this->productPriceRepository->findById($dto->product_price_id);

        $product = $this->productRepository->findFirstWhere([
            'id' => $productPrice->getProductId(),
            'event_id' => $dto->event_id,
        ]);

        if ($product === null) {
            throw new ResourceNotFoundException(__('Product not found for this event'));
        }

        if ($dto->event_occurrence_id !== null) {
            $occurrence = $this->occurrenceRepository->findFirstWhere([
                'id' => $dto->event_occurrence_id,
                'event_id' => $dto->event_id,
            ]);

            if ($occurrence === null || $occurrence->isCancelled() || $occurrence->isPast()) {
                throw ValidationException::withMessages([
                    'event_occurrence_id' => __('This event date is no longer available.'),
                ]);
            }

            $this->occurrenceEligibilityService->assertProductsVisibleOnOccurrence(
                $dto->event_occurrence_id,
                [$product->getId()],
            );
        }

        return $this->createWaitlistEntryService->createEntry($dto, $eventSettings, $product);
    }
}
