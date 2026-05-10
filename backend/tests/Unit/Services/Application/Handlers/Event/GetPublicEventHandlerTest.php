<?php

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventHandler;
use HiEvents\Services\Domain\Event\EventPageViewIncrementService;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class GetPublicEventHandlerTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;

    private EventOccurrenceRepositoryInterface $occurrenceRepository;

    private PromoCodeRepositoryInterface $promoCodeRepository;

    private ProductFilterService $ticketFilterService;

    private EventPageViewIncrementService $eventPageViewIncrementService;

    private GetPublicEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->occurrenceRepository = m::mock(EventOccurrenceRepositoryInterface::class);
        $this->promoCodeRepository = m::mock(PromoCodeRepositoryInterface::class);
        $this->ticketFilterService = m::mock(ProductFilterService::class);
        $this->eventPageViewIncrementService = m::mock(EventPageViewIncrementService::class);

        $this->handler = new GetPublicEventHandler(
            $this->eventRepository,
            $this->occurrenceRepository,
            $this->promoCodeRepository,
            $this->ticketFilterService,
            $this->eventPageViewIncrementService
        );
    }

    public function test_handle_without_promo_code_and_unauthenticated_user(): void
    {
        $data = new GetPublicEventDTO(eventId: 1, isAuthenticated: false, ipAddress: '127.0.0.1', promoCode: null);
        $event = new EventDomainObject;
        $event->setProductCategories(collect());

        $this->setupEventRepositoryMock($event, $data->eventId);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();
        $this->ticketFilterService->shouldReceive('filter')->once()->withAnyArgs()->andReturn(collect());
        $this->eventPageViewIncrementService->shouldReceive('increment')->once()->with($data->eventId, $data->ipAddress);

        $this->handler->handle($data);
    }

    public function test_handle_with_invalid_promo_code(): void
    {
        $data = new GetPublicEventDTO(eventId: 1, isAuthenticated: false, ipAddress: '127.0.0.1', promoCode: 'INVALID');
        $event = new EventDomainObject;
        $event->setProductCategories(collect());
        $promoCode = m::mock(PromoCodeDomainObject::class)->makePartial();
        $promoCode->shouldReceive('isValid')->andReturn(false);

        $this->setupEventRepositoryMock($event, $data->eventId);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturn($promoCode);
        $this->ticketFilterService->shouldReceive('filter')->once()->withAnyArgs()->andReturn(collect());
        $this->eventPageViewIncrementService->shouldReceive('increment')->once()->with($data->eventId, $data->ipAddress);

        $this->handler->handle($data);
    }

    public function test_handle_with_valid_promo_code(): void
    {
        $data = new GetPublicEventDTO(eventId: 1, isAuthenticated: false, ipAddress: '127.0.0.1', promoCode: 'VALID');
        $event = new EventDomainObject;
        $event->setProductCategories(collect());
        $promoCode = m::mock(PromoCodeDomainObject::class)->makePartial();
        $promoCode->shouldReceive('isValid')->andReturn(true);

        $this->setupEventRepositoryMock($event, $data->eventId);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturn($promoCode);
        $this->ticketFilterService->shouldReceive('filter')->once()->withAnyArgs()->andReturn(collect());
        $this->eventPageViewIncrementService->shouldReceive('increment')->once()->with($data->eventId, $data->ipAddress);

        $this->handler->handle($data);
    }

    public function test_handle_loads_single_event_occurrence_without_future_filter(): void
    {
        $data = new GetPublicEventDTO(eventId: 1, isAuthenticated: true, ipAddress: '127.0.0.1', promoCode: null);
        $event = (new EventDomainObject)
            ->setType(EventType::SINGLE->name)
            ->setProductCategories(collect());
        $pastOccurrence = $this->makeOccurrence(10, '2024-01-01 10:00:00');

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf()->times(4);
        $this->eventRepository->shouldReceive('findById')->with($data->eventId)->andReturn($event);
        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with(
                m::on(static fn (array $where): bool => ! collect($where)->contains(
                    fn ($condition): bool => is_array($condition)
                        && ($condition[0] ?? null) === EventOccurrenceDomainObjectAbstract::START_DATE
                )),
                m::any(),
                m::any(),
                m::any(),
            )
            ->andReturn(collect([$pastOccurrence]));
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();
        $this->ticketFilterService->shouldReceive('filter')->once()->withAnyArgs()->andReturn(collect());
        $this->eventPageViewIncrementService->shouldNotReceive('increment');

        $result = $this->handler->handle($data);

        $this->assertTrue($result->getEventOccurrences()->contains(
            fn (EventOccurrenceDomainObject $occurrence) => $occurrence->getId() === 10
        ));
    }

    public function test_handle_ignores_requested_occurrence_when_it_does_not_belong_to_event(): void
    {
        $data = new GetPublicEventDTO(
            eventId: 1,
            isAuthenticated: true,
            ipAddress: '127.0.0.1',
            promoCode: null,
            eventOccurrenceId: 999,
        );
        $event = new EventDomainObject;
        $event->setProductCategories(collect());

        $this->setupEventRepositoryMock($event, $data->eventId);
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => 999,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => 1,
                [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
            ])
            ->andReturnNull();
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();

        $capturedOccurrenceId = 'not-called';
        $this->ticketFilterService
            ->shouldReceive('filter')
            ->once()
            ->andReturnUsing(function (
                Collection $productsCategories,
                ?PromoCodeDomainObject $promoCode = null,
                bool $hideSoldOutProducts = true,
                ?int $eventOccurrenceId = null,
            ) use (&$capturedOccurrenceId) {
                $capturedOccurrenceId = $eventOccurrenceId;

                return collect();
            });
        $this->eventPageViewIncrementService->shouldNotReceive('increment');

        $this->handler->handle($data);

        $this->assertNull($capturedOccurrenceId);
    }

    public function test_handle_keeps_requested_occurrence_outside_public_cap(): void
    {
        // The production query loads MAX_PUBLIC_OCCURRENCES + 1 (= 201) rows so
        // the handler can detect overflow without paginating the whole
        // recurrence series. A direct link to occurrence 5000 must therefore
        // be resolved via findFirstWhere() and stitched back into the payload.
        $linkedOccurrenceId = 5000;
        $data = new GetPublicEventDTO(
            eventId: 1,
            isAuthenticated: true,
            ipAddress: '127.0.0.1',
            promoCode: null,
            eventOccurrenceId: $linkedOccurrenceId,
        );
        $event = new EventDomainObject;
        $event->setProductCategories(collect());

        $loadedLimit = GetPublicEventHandler::MAX_PUBLIC_OCCURRENCES + 1;
        $occurrences = collect(range(1, $loadedLimit))
            ->map(fn (int $id) => $this->makeOccurrence($id, '2026-01-01 10:00:00'));
        $linkedOccurrence = $this->makeOccurrence($linkedOccurrenceId, '2027-01-01 10:00:00');

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf()->times(4);
        $this->eventRepository->shouldReceive('findById')->with($data->eventId)->andReturn($event);
        // findWhere has signature ($where, $columns, $orderAndDirections, $limit)
        // — production passes the limit by name, but Mockery sees them as
        // positional, so we need to assert against arg index 3.
        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with(m::any(), m::any(), m::any(), m::on(static fn ($limit) => $limit === $loadedLimit))
            ->andReturn($occurrences);
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                EventOccurrenceDomainObjectAbstract::ID => $linkedOccurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => 1,
                [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
            ])
            ->andReturn($linkedOccurrence);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();

        $capturedOccurrenceId = null;
        $this->ticketFilterService
            ->shouldReceive('filter')
            ->once()
            ->andReturnUsing(function (
                Collection $productsCategories,
                ?PromoCodeDomainObject $promoCode = null,
                bool $hideSoldOutProducts = true,
                ?int $eventOccurrenceId = null,
            ) use (&$capturedOccurrenceId) {
                $capturedOccurrenceId = $eventOccurrenceId;

                return collect();
            });
        $this->eventPageViewIncrementService->shouldNotReceive('increment');

        $result = $this->handler->handle($data);

        $this->assertSame($linkedOccurrenceId, $capturedOccurrenceId);
        $this->assertTrue(
            $result->getEventOccurrences()->contains(
                fn (EventOccurrenceDomainObject $occurrence) => $occurrence->getId() === $linkedOccurrenceId
            )
        );
        // 200 capped + 1 linked appended = MAX_PUBLIC_OCCURRENCES + 1.
        $this->assertCount(GetPublicEventHandler::MAX_PUBLIC_OCCURRENCES + 1, $result->getEventOccurrences());
    }

    private function setupEventRepositoryMock($event, $eventId): void
    {
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf()->times(4);
        $this->eventRepository->shouldReceive('findById')->with($eventId)->andReturn($event);
        $this->occurrenceRepository->shouldReceive('findWhere')->andReturn(collect());
    }

    private function makeOccurrence(int $id, string $startDate): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)
            ->setId($id)
            ->setEventId(1)
            ->setShortId((string) $id)
            ->setStartDate($startDate)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);
    }
}
