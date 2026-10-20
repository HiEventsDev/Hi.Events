<?php

namespace Tests\Unit\Listeners\Waitlist;

use Closure;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Listeners\Waitlist\ProcessWaitlistOnCapacityAvailableListener;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Waitlist\ProcessWaitlistService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class ProcessWaitlistOnCapacityAvailableListenerTest extends TestCase
{
    public function test_an_event_queued_by_a_previous_release_processes_without_an_occurrence(): void
    {
        $event = (new ReflectionClass(CapacityChangedEvent::class))->newInstanceWithoutConstructor();
        Closure::bind(function (): void {
            $this->eventId = 10;
            $this->direction = CapacityChangeDirection::INCREASED;
            $this->productId = null;
            $this->productPriceId = null;
            $this->newCapacity = null;
        }, $event, CapacityChangedEvent::class)();

        $eventDomainObject = (new EventDomainObject)
            ->setEventSettings((new EventSettingDomainObject)->setWaitlistAutoProcess(true));

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->with(10)->andReturn($eventDomainObject);

        $quantitiesService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $quantitiesService->shouldReceive('getAvailableProductQuantities')
            ->once()
            ->with(10, true, null)
            ->andReturn(new AvailableProductQuantitiesResponseDTO(productQuantities: collect()));

        $listener = new ProcessWaitlistOnCapacityAvailableListener(
            $eventRepository,
            Mockery::mock(ProcessWaitlistService::class),
            $quantitiesService,
        );

        $listener->handle($event);
    }
}
