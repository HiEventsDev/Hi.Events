<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductOccurrenceVisibilityDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\WaitlistEntryDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\Dispatcher;
use HiEvents\Events\EventUpdateEvent;
use HiEvents\Exceptions\CannotChangeEventTypeException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Event\Webhook\DispatchEventWebhookJob;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventTypeDTO;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class UpdateEventTypeHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $priceOverrideRepository,
        private readonly ProductOccurrenceVisibilityRepositoryInterface $productVisibilityRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly TransactionLockService $transactionLockService,
        private readonly Dispatcher $dispatcher,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws CannotChangeEventTypeException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(UpdateEventTypeDTO $dto): EventDomainObject
    {
        $changed = $this->databaseManager->transaction(function () use ($dto): bool {
            $this->transactionLockService->lockEvent($dto->event_id);

            $event = $this->eventRepository->findFirstWhere([
                EventDomainObjectAbstract::ID => $dto->event_id,
                EventDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            ]);

            if ($event === null) {
                throw new ResourceNotFoundException(__('Event :id not found', ['id' => $dto->event_id]));
            }

            if ($event->getType() === $dto->type->name) {
                return false;
            }

            if ($dto->type === EventType::SINGLE) {
                $this->convertToSingle($event);
            } else {
                $this->convertToRecurring($event);
            }

            return true;
        });

        $event = $this->loadEvent($dto);

        if ($changed) {
            $this->dispatcher->dispatchEvent(new EventUpdateEvent($event));
            DispatchEventWebhookJob::dispatch($event->getId(), DomainEventType::EVENT_UPDATED);
        }

        return $event;
    }

    /**
     * @throws CannotChangeEventTypeException
     */
    private function convertToSingle(EventDomainObject $event): void
    {
        $occurrences = $this->occurrenceRepository->findWhere([
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $event->getId(),
        ]);

        if ($occurrences->isEmpty()) {
            throw new CannotChangeEventTypeException(
                __('Add a date before changing to a single event.'),
            );
        }

        if ($occurrences->count() > 1) {
            throw new CannotChangeEventTypeException(
                __('Only a recurring event with exactly one date can be changed to a single event. Delete the other dates first.'),
            );
        }

        /** @var EventOccurrenceDomainObject $occurrence */
        $occurrence = $occurrences->first();

        if ($occurrence->isCancelled()) {
            throw new CannotChangeEventTypeException(
                __('The remaining date is cancelled. Reactivate it before changing to a single event.'),
            );
        }

        if ($this->hasOrders($event->getId())) {
            throw new CannotChangeEventTypeException(
                __('A recurring event with orders cannot be changed to a single event.'),
            );
        }

        $this->priceOverrideRepository->deleteWhere([
            ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrence->getId(),
        ]);

        $this->productVisibilityRepository->deleteWhere([
            ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrence->getId(),
        ]);

        $this->occurrenceRepository->updateFromArray($occurrence->getId(), [
            EventOccurrenceDomainObjectAbstract::CAPACITY => null,
            EventOccurrenceDomainObjectAbstract::LABEL => null,
            EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID => null,
            EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => false,
        ]);

        $this->checkInListRepository->updateWhere(
            attributes: [CheckInListDomainObjectAbstract::EVENT_OCCURRENCE_ID => null],
            where: [CheckInListDomainObjectAbstract::EVENT_ID => $event->getId()],
        );

        $this->applyEventWideProductQuantities($event->getId());

        $this->eventRepository->updateFromArray($event->getId(), [
            EventDomainObjectAbstract::TYPE => EventType::SINGLE->name,
            EventDomainObjectAbstract::RECURRENCE_RULE => null,
            EventDomainObjectAbstract::EVENT_LOCATION_ID => $occurrence->getEventLocationId() ?? $event->getEventLocationId(),
        ]);
    }

    private function convertToRecurring(EventDomainObject $event): void
    {
        $occurrences = $this->occurrenceRepository->findWhere([
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $event->getId(),
        ]);

        if ($occurrences->count() === 1) {
            $this->waitlistEntryRepository->updateWhere(
                attributes: [WaitlistEntryDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrences->first()->getId()],
                where: [
                    WaitlistEntryDomainObjectAbstract::EVENT_ID => $event->getId(),
                    [WaitlistEntryDomainObjectAbstract::EVENT_OCCURRENCE_ID, 'null', null],
                ],
            );
        }

        $this->eventRepository->updateFromArray($event->getId(), [
            EventDomainObjectAbstract::TYPE => EventType::RECURRING->name,
        ]);
    }

    private function applyEventWideProductQuantities(int $eventId): void
    {
        $productIds = $this->productRepository
            ->findWhere([ProductDomainObjectAbstract::EVENT_ID => $eventId])
            ->map(fn (ProductDomainObject $product) => $product->getId())
            ->all();

        if ($productIds === []) {
            return;
        }

        $this->productPriceRepository->updateWhere(
            attributes: [ProductPriceDomainObjectAbstract::QUANTITY_APPLIES_TO => ProductQuantityAppliesTo::EVENT->name],
            where: [[ProductPriceDomainObjectAbstract::PRODUCT_ID, 'in', $productIds]],
        );
    }

    private function hasOrders(int $eventId): bool
    {
        return $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::EVENT_ID => $eventId,
            static fn (Builder $query) => $query
                ->whereIn(OrderDomainObjectAbstract::STATUS, [
                    OrderStatus::COMPLETED->name,
                    OrderStatus::CANCELLED->name,
                    OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
                ])
                ->orWhere(static fn (Builder $reserved) => $reserved
                    ->where(OrderDomainObjectAbstract::STATUS, OrderStatus::RESERVED->name)
                    ->where(OrderDomainObjectAbstract::RESERVED_UNTIL, '>', now())),
        ]) !== null;
    }

    private function loadEvent(UpdateEventTypeDTO $dto): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, nested: [
                new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                ], name: 'event_location'),
            ]))
            ->findFirstWhere([
                EventDomainObjectAbstract::ID => $dto->event_id,
                EventDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            ]);
    }
}
