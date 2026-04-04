<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\CreateEventOccurrenceHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateEventOccurrenceHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;
    private DatabaseManager|MockInterface $databaseManager;
    private CreateEventOccurrenceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new CreateEventOccurrenceHandler(
            $this->occurrenceRepository,
            $this->databaseManager,
        );
    }

    public function testHandleSuccessfullyCreatesOccurrence(): void
    {
        $dto = new UpsertEventOccurrenceDTO(
            event_id: 1,
            start_date: '2026-06-01 10:00:00',
            end_date: '2026-06-01 18:00:00',
            status: EventOccurrenceStatus::ACTIVE->name,
            capacity: 100,
            label: 'Morning Session',
            is_overridden: false,
        );

        $expectedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($attrs) {
                return $attrs[EventOccurrenceDomainObjectAbstract::EVENT_ID] === 1
                    && $attrs[EventOccurrenceDomainObjectAbstract::START_DATE] === '2026-06-01 10:00:00'
                    && $attrs[EventOccurrenceDomainObjectAbstract::END_DATE] === '2026-06-01 18:00:00'
                    && $attrs[EventOccurrenceDomainObjectAbstract::STATUS] === EventOccurrenceStatus::ACTIVE->name
                    && $attrs[EventOccurrenceDomainObjectAbstract::CAPACITY] === 100
                    && $attrs[EventOccurrenceDomainObjectAbstract::USED_CAPACITY] === 0
                    && $attrs[EventOccurrenceDomainObjectAbstract::LABEL] === 'Morning Session'
                    && str_starts_with($attrs[EventOccurrenceDomainObjectAbstract::SHORT_ID], 'oc_');
            }))
            ->andReturn($expectedOccurrence);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOccurrence, $result);
    }

    public function testHandleDefaultsStatusToActiveWhenNotProvided(): void
    {
        $dto = new UpsertEventOccurrenceDTO(
            event_id: 2,
            start_date: '2026-07-01 09:00:00',
            end_date: null,
            status: null,
            capacity: null,
        );

        $expectedOccurrence = Mockery::mock(EventOccurrenceDomainObject::class);

        $this->occurrenceRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($attrs) {
                return $attrs[EventOccurrenceDomainObjectAbstract::EVENT_ID] === 2
                    && $attrs[EventOccurrenceDomainObjectAbstract::START_DATE] === '2026-07-01 09:00:00'
                    && $attrs[EventOccurrenceDomainObjectAbstract::END_DATE] === null
                    && $attrs[EventOccurrenceDomainObjectAbstract::STATUS] === EventOccurrenceStatus::ACTIVE->name
                    && str_starts_with($attrs[EventOccurrenceDomainObjectAbstract::SHORT_ID], 'oc_');
            }))
            ->andReturn($expectedOccurrence);

        $result = $this->handler->handle($dto);

        $this->assertSame($expectedOccurrence, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
