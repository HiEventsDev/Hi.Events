<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Tests\TestCase;

class GenerateOccurrencesFromRuleHandlerTest extends TestCase
{
    private EventOccurrenceGeneratorService|Mockery\MockInterface $generatorService;

    private EventRepositoryInterface|Mockery\MockInterface $eventRepository;

    private DatabaseManager|Mockery\MockInterface $databaseManager;

    private GenerateOccurrencesFromRuleHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generatorService = Mockery::mock(EventOccurrenceGeneratorService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());
        $this->databaseManager->shouldReceive('statement')
            ->with('SELECT pg_advisory_xact_lock(?)', [1])
            ->byDefault();

        $this->handler = new GenerateOccurrencesFromRuleHandler(
            $this->generatorService,
            $this->eventRepository,
            $this->databaseManager,
        );
    }

    public function test_handle_generates_occurrences_and_updates_event_type(): void
    {
        $rule = ['frequency' => 'weekly', 'range' => ['type' => 'count', 'count' => 10]];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $rule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn(1);
        $event->shouldReceive('getRecurrenceRule')->andReturn(null);
        $event->shouldReceive('setRecurrenceRule')->once()->with($rule);

        $this->eventRepository->shouldReceive('findByIdLocked')->with(1)->once()->andReturn($event);

        $this->eventRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(1, [
                EventDomainObjectAbstract::RECURRENCE_RULE => $rule,
                EventDomainObjectAbstract::TYPE => EventType::RECURRING->name,
            ]);

        $this->generatorService->shouldReceive('generate')
            ->once()
            ->with($event, $rule);

        $this->handler->handle($dto);
    }

    public function test_handle_takes_event_advisory_lock(): void
    {
        $rule = ['frequency' => 'weekly'];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $rule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn(1);
        $event->shouldReceive('getRecurrenceRule')->andReturn(null);
        $event->shouldReceive('setRecurrenceRule')->once();

        $this->databaseManager->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?)', [1]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldReceive('updateFromArray')->once();
        $this->generatorService->shouldReceive('generate')->once();

        $this->handler->handle($dto);
    }

    public function test_handle_merges_live_exclusions_into_submitted_rule(): void
    {
        $submittedRule = [
            'frequency' => 'weekly',
            'excluded_occurrences' => ['2026-08-01 19:00'],
        ];
        $liveRule = [
            'frequency' => 'weekly',
            'excluded_occurrences' => ['2026-08-08 19:00'],
            'excluded_dates' => ['2026-09-01'],
        ];
        $expectedRule = [
            'frequency' => 'weekly',
            'excluded_occurrences' => ['2026-08-08 19:00', '2026-08-01 19:00'],
            'excluded_dates' => ['2026-09-01'],
        ];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $submittedRule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn(1);
        $event->shouldReceive('getRecurrenceRule')->andReturn($liveRule);
        $event->shouldReceive('setRecurrenceRule')->once()->with($expectedRule);

        $this->eventRepository->shouldReceive('findByIdLocked')->with(1)->once()->andReturn($event);

        $this->eventRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(1, [
                EventDomainObjectAbstract::RECURRENCE_RULE => $expectedRule,
                EventDomainObjectAbstract::TYPE => EventType::RECURRING->name,
            ]);

        $this->generatorService->shouldReceive('generate')
            ->once()
            ->with($event, $expectedRule);

        $this->handler->handle($dto);
    }

    public function test_handle_merges_additional_dates_without_collapsing_them(): void
    {
        $submittedRule = [
            'frequency' => 'weekly',
            'additional_dates' => [
                ['date' => '2026-08-01', 'time' => '10:00'],
                ['date' => '2026-08-02', 'time' => '10:00'],
            ],
        ];
        $liveRule = [
            'frequency' => 'weekly',
            'additional_dates' => [
                ['date' => '2026-08-02', 'time' => '10:00'],
                ['date' => '2026-08-03'],
            ],
        ];
        $expectedRule = [
            'frequency' => 'weekly',
            'additional_dates' => [
                ['date' => '2026-08-02', 'time' => '10:00'],
                ['date' => '2026-08-03'],
                ['date' => '2026-08-01', 'time' => '10:00'],
            ],
        ];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $submittedRule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn(1);
        $event->shouldReceive('getRecurrenceRule')->andReturn($liveRule);
        $event->shouldReceive('setRecurrenceRule')->once()->with($expectedRule);

        $this->eventRepository->shouldReceive('findByIdLocked')->with(1)->once()->andReturn($event);

        $this->eventRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(1, [
                EventDomainObjectAbstract::RECURRENCE_RULE => $expectedRule,
                EventDomainObjectAbstract::TYPE => EventType::RECURRING->name,
            ]);

        $this->generatorService->shouldReceive('generate')
            ->once()
            ->with($event, $expectedRule);

        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
