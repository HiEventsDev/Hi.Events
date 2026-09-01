<?php

namespace Tests\Unit\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\PartialEditAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\PartialEditAttendeeHandler;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsReactivationService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class PartialEditAttendeeHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ORDER_ID = 20;

    private const ATTENDEE_ID = 30;

    private const PRODUCT_ID = 40;

    private const PRODUCT_PRICE_ID = 41;

    private const OCCURRENCE_ID = 50;

    private const ORDER_CREATED_AT = '2026-08-20 10:00:00';

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private ProductQuantityUpdateService|MockInterface $productQuantityService;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private EventStatisticsCancellationService|MockInterface $cancellationService;

    private EventStatisticsReactivationService|MockInterface $reactivationService;

    private PartialEditAttendeeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->productQuantityService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->cancellationService = Mockery::mock(EventStatisticsCancellationService::class);
        $this->reactivationService = Mockery::mock(EventStatisticsReactivationService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new PartialEditAttendeeHandler(
            $this->attendeeRepository,
            $this->orderRepository,
            $this->productQuantityService,
            $databaseManager,
            $this->domainEventDispatcherService,
            $this->cancellationService,
            $this->reactivationService,
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
        );
    }

    public function test_cancelling_an_active_attendee_releases_capacity_and_decrements_statistics(): void
    {
        $this->givenAttendee(AttendeeStatus::ACTIVE);
        $this->givenOrderExists();
        $this->expectAttendeeUpdatedWithStatus(AttendeeStatus::CANCELLED);

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')
            ->once()
            ->with(self::PRODUCT_PRICE_ID, 1, self::OCCURRENCE_ID);
        $this->productQuantityService->shouldNotReceive('increaseQuantitySold');

        $this->cancellationService->shouldReceive('decrementForCancelledAttendee')
            ->once()
            ->with(self::EVENT_ID, self::ORDER_CREATED_AT, 1, self::OCCURRENCE_ID);
        $this->reactivationService->shouldNotReceive('incrementForReactivatedAttendee');

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(
                fn (AttendeeEvent $event) => $event->type === DomainEventType::ATTENDEE_CANCELLED
                    && $event->attendeeId === self::ATTENDEE_ID
            ));

        $this->handler->handle($this->dto(AttendeeStatus::CANCELLED));

        Event::assertDispatched(
            CapacityChangedEvent::class,
            fn (CapacityChangedEvent $event) => $event->direction === CapacityChangeDirection::INCREASED
                && $event->eventId === self::EVENT_ID
                && $event->productPriceId === self::PRODUCT_PRICE_ID
                && $event->eventOccurrenceId === self::OCCURRENCE_ID
        );
    }

    public function test_reactivating_a_cancelled_attendee_consumes_capacity_and_increments_statistics(): void
    {
        $this->givenAttendee(AttendeeStatus::CANCELLED);
        $this->givenOrderExists();
        $this->expectAttendeeUpdatedWithStatus(AttendeeStatus::ACTIVE);

        $this->productQuantityService->shouldReceive('increaseQuantitySold')
            ->once()
            ->with(self::PRODUCT_PRICE_ID, 1, self::OCCURRENCE_ID);
        $this->productQuantityService->shouldNotReceive('decreaseQuantitySold');

        $this->reactivationService->shouldReceive('incrementForReactivatedAttendee')
            ->once()
            ->with(self::EVENT_ID, self::ORDER_CREATED_AT, 1, self::OCCURRENCE_ID);
        $this->cancellationService->shouldNotReceive('decrementForCancelledAttendee');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');

        $this->handler->handle($this->dto(AttendeeStatus::ACTIVE));

        Event::assertDispatched(
            CapacityChangedEvent::class,
            fn (CapacityChangedEvent $event) => $event->direction === CapacityChangeDirection::DECREASED
                && $event->productPriceId === self::PRODUCT_PRICE_ID
                && $event->eventOccurrenceId === self::OCCURRENCE_ID
        );
    }

    public function test_cancelling_with_a_lowercase_status_releases_capacity_and_decrements_statistics(): void
    {
        $this->givenAttendee(AttendeeStatus::ACTIVE);
        $this->givenOrderExists();
        $this->expectAttendeeUpdatedWithStatus(AttendeeStatus::CANCELLED);

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')
            ->once()
            ->with(self::PRODUCT_PRICE_ID, 1, self::OCCURRENCE_ID);

        $this->cancellationService->shouldReceive('decrementForCancelledAttendee')
            ->once()
            ->with(self::EVENT_ID, self::ORDER_CREATED_AT, 1, self::OCCURRENCE_ID);

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(
                fn (AttendeeEvent $event) => $event->type === DomainEventType::ATTENDEE_CANCELLED
                    && $event->attendeeId === self::ATTENDEE_ID
            ));

        $this->handler->handle(new PartialEditAttendeeDTO(
            attendee_id: self::ATTENDEE_ID,
            event_id: self::EVENT_ID,
            first_name: null,
            last_name: null,
            email: null,
            status: 'cancelled',
        ));

        Event::assertDispatched(
            CapacityChangedEvent::class,
            fn (CapacityChangedEvent $event) => $event->direction === CapacityChangeDirection::INCREASED
        );
    }

    public function test_setting_the_same_status_in_lowercase_does_not_touch_capacity_or_statistics(): void
    {
        $this->givenAttendee(AttendeeStatus::ACTIVE);
        $this->expectAttendeeUpdatedWithStatus(AttendeeStatus::ACTIVE);

        $this->productQuantityService->shouldNotReceive('increaseQuantitySold');
        $this->productQuantityService->shouldNotReceive('decreaseQuantitySold');
        $this->cancellationService->shouldNotReceive('decrementForCancelledAttendee');
        $this->reactivationService->shouldNotReceive('incrementForReactivatedAttendee');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');
        $this->orderRepository->shouldNotReceive('findFirstWhere');

        $this->handler->handle(new PartialEditAttendeeDTO(
            attendee_id: self::ATTENDEE_ID,
            event_id: self::EVENT_ID,
            first_name: null,
            last_name: null,
            email: null,
            status: 'active',
        ));

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_setting_the_same_status_does_not_touch_capacity_or_statistics(): void
    {
        $this->givenAttendee(AttendeeStatus::ACTIVE);
        $this->expectAttendeeUpdatedWithStatus(AttendeeStatus::ACTIVE);

        $this->productQuantityService->shouldNotReceive('increaseQuantitySold');
        $this->productQuantityService->shouldNotReceive('decreaseQuantitySold');
        $this->cancellationService->shouldNotReceive('decrementForCancelledAttendee');
        $this->reactivationService->shouldNotReceive('incrementForReactivatedAttendee');
        $this->domainEventDispatcherService->shouldNotReceive('dispatch');
        $this->orderRepository->shouldNotReceive('findFirstWhere');

        $this->handler->handle($this->dto(AttendeeStatus::ACTIVE));

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_editing_only_contact_details_does_not_touch_capacity_or_statistics(): void
    {
        $this->givenAttendee(AttendeeStatus::ACTIVE);

        $this->attendeeRepository->shouldReceive('updateByIdWhere')
            ->once()
            ->withArgs(function (int $id, array $attributes, array $where): bool {
                return $id === self::ATTENDEE_ID
                    && $attributes['status'] === AttendeeStatus::ACTIVE->name
                    && $attributes['first_name'] === 'Renamed'
                    && $attributes['last_name'] === 'Last'
                    && $attributes['email'] === 'original@example.com'
                    && $where === ['event_id' => self::EVENT_ID];
            })
            ->andReturn(new AttendeeDomainObject);

        $this->productQuantityService->shouldNotReceive('increaseQuantitySold');
        $this->productQuantityService->shouldNotReceive('decreaseQuantitySold');
        $this->cancellationService->shouldNotReceive('decrementForCancelledAttendee');
        $this->reactivationService->shouldNotReceive('incrementForReactivatedAttendee');

        $this->handler->handle(new PartialEditAttendeeDTO(
            attendee_id: self::ATTENDEE_ID,
            event_id: self::EVENT_ID,
            first_name: 'Renamed',
            last_name: null,
            email: null,
            status: null,
        ));

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_cancelling_an_attendee_without_an_occurrence_decrements_event_level_statistics_only(): void
    {
        $this->givenAttendee(AttendeeStatus::ACTIVE, occurrenceId: null);
        $this->givenOrderExists();
        $this->expectAttendeeUpdatedWithStatus(AttendeeStatus::CANCELLED);

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')
            ->once()
            ->with(self::PRODUCT_PRICE_ID, 1, null);
        $this->cancellationService->shouldReceive('decrementForCancelledAttendee')
            ->once()
            ->with(self::EVENT_ID, self::ORDER_CREATED_AT, 1, null);
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->handler->handle($this->dto(AttendeeStatus::CANCELLED));
    }

    public function test_capacity_is_still_released_when_the_order_cannot_be_found(): void
    {
        $this->givenAttendee(AttendeeStatus::ACTIVE);
        $this->orderRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();
        $this->expectAttendeeUpdatedWithStatus(AttendeeStatus::CANCELLED);

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once();
        $this->cancellationService->shouldNotReceive('decrementForCancelledAttendee');
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->handler->handle($this->dto(AttendeeStatus::CANCELLED));
    }

    public function test_throws_when_attendee_does_not_belong_to_event(): void
    {
        $this->attendeeRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => self::ATTENDEE_ID, 'event_id' => self::EVENT_ID])
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($this->dto(AttendeeStatus::CANCELLED));
    }

    private function dto(AttendeeStatus $status): PartialEditAttendeeDTO
    {
        return new PartialEditAttendeeDTO(
            attendee_id: self::ATTENDEE_ID,
            event_id: self::EVENT_ID,
            first_name: null,
            last_name: null,
            email: null,
            status: $status->name,
        );
    }

    private function givenAttendee(AttendeeStatus $status, ?int $occurrenceId = self::OCCURRENCE_ID): void
    {
        $attendee = (new AttendeeDomainObject)
            ->setId(self::ATTENDEE_ID)
            ->setEventId(self::EVENT_ID)
            ->setOrderId(self::ORDER_ID)
            ->setProductId(self::PRODUCT_ID)
            ->setProductPriceId(self::PRODUCT_PRICE_ID)
            ->setEventOccurrenceId($occurrenceId)
            ->setStatus($status->name)
            ->setFirstName('Original')
            ->setLastName('Last')
            ->setEmail('original@example.com');

        $this->attendeeRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => self::ATTENDEE_ID, 'event_id' => self::EVENT_ID])
            ->andReturn($attendee);
    }

    private function givenOrderExists(): void
    {
        $this->orderRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => self::ORDER_ID, 'event_id' => self::EVENT_ID])
            ->andReturn((new OrderDomainObject)->setId(self::ORDER_ID)->setCreatedAt(self::ORDER_CREATED_AT));
    }

    private function expectAttendeeUpdatedWithStatus(AttendeeStatus $status): void
    {
        $this->attendeeRepository->shouldReceive('updateByIdWhere')
            ->once()
            ->withArgs(fn (int $id, array $attributes, array $where): bool => $id === self::ATTENDEE_ID
                && $attributes['status'] === $status->name
                && $where === ['event_id' => self::EVENT_ID])
            ->andReturn(new AttendeeDomainObject);
    }
}
