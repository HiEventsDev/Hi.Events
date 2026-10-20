<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use Closure;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\InvalidOccurrenceDatesException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GetPublicEventOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetPublicEventOccurrencesHandler;
use HiEvents\Services\Domain\EventOccurrence\PublicOccurrenceVisibilityService;
use Mockery as m;
use Tests\TestCase;

class GetPublicEventOccurrencesHandlerTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;

    private EventOccurrenceRepositoryInterface $occurrenceRepository;

    private GetPublicEventOccurrencesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->occurrenceRepository = m::mock(EventOccurrenceRepositoryInterface::class);

        $this->handler = new GetPublicEventOccurrencesHandler(
            $this->eventRepository,
            $this->occurrenceRepository,
            new PublicOccurrenceVisibilityService,
        );
    }

    public function test_handle_throws_when_range_is_missing(): void
    {
        $this->expectException(InvalidOccurrenceDatesException::class);

        $this->handler->handle(new GetPublicEventOccurrencesDTO(
            eventId: 1,
            startDateFrom: '2026-08-01 00:00:00',
            startDateTo: null,
        ));
    }

    public function test_handle_throws_when_range_is_unparseable(): void
    {
        $this->expectException(InvalidOccurrenceDatesException::class);

        $this->handler->handle(new GetPublicEventOccurrencesDTO(
            eventId: 1,
            startDateFrom: 'not-a-date',
            startDateTo: '2026-08-31 23:59:59',
        ));
    }

    public function test_handle_throws_when_range_is_inverted(): void
    {
        $this->expectException(InvalidOccurrenceDatesException::class);

        $this->handler->handle(new GetPublicEventOccurrencesDTO(
            eventId: 1,
            startDateFrom: '2026-09-01 00:00:00',
            startDateTo: '2026-08-01 00:00:00',
        ));
    }

    public function test_handle_throws_when_range_exceeds_maximum_span(): void
    {
        $this->expectException(InvalidOccurrenceDatesException::class);

        $this->handler->handle(new GetPublicEventOccurrencesDTO(
            eventId: 1,
            startDateFrom: '2026-08-01 00:00:00',
            startDateTo: '2026-10-01 00:00:00',
        ));
    }

    public function test_handle_returns_occurrences_within_range(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setType(EventType::RECURRING->name)
            ->setEventSettings((new EventSettingDomainObject)->setHideSoldOutOccurrences(false))
            ->setProductCategories(collect());

        $occurrence = (new EventOccurrenceDomainObject)
            ->setId(10)
            ->setEventId(1)
            ->setStartDate('2026-08-10 10:00:00')
            ->setStatus(EventOccurrenceStatus::ACTIVE->name);

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->with(1)->andReturn($event);

        $capturedWhere = null;
        $capturedLimit = null;
        $this->occurrenceRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturnUsing(static function (array $where, $columns = null, $orders = null, $limit = null) use (&$capturedWhere, &$capturedLimit, $occurrence) {
                $capturedWhere = $where;
                $capturedLimit = $limit;

                return collect([$occurrence]);
            });

        $result = $this->handler->handle(new GetPublicEventOccurrencesDTO(
            eventId: 1,
            startDateFrom: '2026-08-01 00:00:00',
            startDateTo: '2026-08-31 23:59:59',
        ));

        $this->assertSame($event, $result->event);
        $this->assertTrue($result->occurrences->contains(
            fn (EventOccurrenceDomainObject $o) => $o->getId() === 10
        ));
        $this->assertSame(GetPublicEventHandler::MAX_PUBLIC_OCCURRENCES, $capturedLimit);

        $bounds = collect($capturedWhere)
            ->filter(static fn ($condition): bool => is_array($condition)
                && ($condition[0] ?? null) === EventOccurrenceDomainObjectAbstract::START_DATE)
            ->values();

        $this->assertSame(['>=', '2026-08-01 00:00:00'], [$bounds[0][1], $bounds[0][2]]);
        $this->assertSame(['<=', '2026-08-31 23:59:59'], [$bounds[1][1], $bounds[1][2]]);
    }

    public function test_handle_applies_sold_out_filter_when_event_hides_sold_out_occurrences(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setType(EventType::RECURRING->name)
            ->setEventSettings((new EventSettingDomainObject)->setHideSoldOutOccurrences(true))
            ->setProductCategories(collect());

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->with(1)->andReturn($event);

        $this->occurrenceRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with(
                m::on(static fn (array $where): bool => collect($where)->filter(
                    static fn ($condition): bool => $condition instanceof Closure
                )->count() === 2),
                m::any(),
                m::any(),
                m::any(),
            )
            ->andReturn(collect());

        $result = $this->handler->handle(new GetPublicEventOccurrencesDTO(
            eventId: 1,
            startDateFrom: '2026-08-01 00:00:00',
            startDateTo: '2026-08-31 23:59:59',
        ));

        $this->assertCount(0, $result->occurrences);
    }
}
