<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use HiEvents\Services\Domain\Event\RecurrenceRuleParserService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class GenerateOccurrencesFromRuleHandlerTest extends TestCase
{
    private EventOccurrenceGeneratorService|Mockery\MockInterface $generatorService;
    private EventRepositoryInterface|Mockery\MockInterface $eventRepository;
    private RecurrenceRuleParserService|Mockery\MockInterface $ruleParserService;
    private DatabaseManager|Mockery\MockInterface $databaseManager;
    private GenerateOccurrencesFromRuleHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generatorService = Mockery::mock(EventOccurrenceGeneratorService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->ruleParserService = Mockery::mock(RecurrenceRuleParserService::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);

        $this->databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->handler = new GenerateOccurrencesFromRuleHandler(
            $this->generatorService,
            $this->eventRepository,
            $this->ruleParserService,
            $this->databaseManager,
        );
    }

    public function testHandleGeneratesOccurrencesAndUpdatesEventType(): void
    {
        $rule = ['frequency' => 'weekly', 'range' => ['type' => 'count', 'count' => 10]];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $rule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('America/New_York');
        $event->shouldReceive('getId')->andReturn(1);
        $event->shouldReceive('setRecurrenceRule')->once()->with($rule);

        $this->eventRepository->shouldReceive('findById')->with(1)->once()->andReturn($event);

        $this->ruleParserService->shouldReceive('parse')
            ->with($rule, 'America/New_York')
            ->once()
            ->andReturn(collect(range(1, 10)));

        $this->eventRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(1, [
                EventDomainObjectAbstract::RECURRENCE_RULE => $rule,
                EventDomainObjectAbstract::TYPE => EventType::RECURRING->name,
            ]);

        $generatedOccurrences = collect(['occ1', 'occ2']);
        $this->generatorService->shouldReceive('generate')
            ->once()
            ->with($event, $rule)
            ->andReturn($generatedOccurrences);

        $result = $this->handler->handle($dto);

        $this->assertSame($generatedOccurrences, $result);
    }

    public function testHandleThrowsValidationExceptionWhenTooManyOccurrences(): void
    {
        $rule = ['frequency' => 'daily', 'range' => ['type' => 'count', 'count' => 2000]];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $rule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');

        $this->eventRepository->shouldReceive('findById')->with(1)->once()->andReturn($event);

        $this->ruleParserService->shouldReceive('parse')
            ->with($rule, 'UTC')
            ->once()
            ->andReturn(collect(range(1, RecurrenceRuleParserService::MAX_OCCURRENCES)));

        $this->generatorService->shouldNotReceive('generate');

        $this->expectException(ValidationException::class);

        $this->handler->handle($dto);
    }

    public function testHandleUsesUtcWhenEventHasNoTimezone(): void
    {
        $rule = ['frequency' => 'weekly'];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $rule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn(null);
        $event->shouldReceive('getId')->andReturn(1);
        $event->shouldReceive('setRecurrenceRule')->once();

        $this->eventRepository->shouldReceive('findById')->once()->andReturn($event);

        $this->ruleParserService->shouldReceive('parse')
            ->with($rule, 'UTC')
            ->once()
            ->andReturn(collect(range(1, 5)));

        $this->eventRepository->shouldReceive('updateFromArray')->once();
        $this->generatorService->shouldReceive('generate')->once()->andReturn(collect());

        $result = $this->handler->handle($dto);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
