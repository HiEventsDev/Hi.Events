<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Jobs\Occurrence\GenerateOccurrencesJob;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\StartOccurrenceGenerationHandler;
use HiEvents\Services\Domain\Event\RecurrenceRuleParserService;
use HiEvents\Services\Infrastructure\Jobs\DTO\JobPollingResultDTO;
use HiEvents\Services\Infrastructure\Jobs\Enum\JobStatusEnum;
use HiEvents\Services\Infrastructure\Jobs\JobPollingService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class StartOccurrenceGenerationHandlerTest extends TestCase
{
    private EventRepositoryInterface|Mockery\MockInterface $eventRepository;

    private RecurrenceRuleParserService|Mockery\MockInterface $ruleParserService;

    private JobPollingService|Mockery\MockInterface $jobPollingService;

    private StartOccurrenceGenerationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->ruleParserService = Mockery::mock(RecurrenceRuleParserService::class);
        $this->jobPollingService = Mockery::mock(JobPollingService::class);

        $this->handler = new StartOccurrenceGenerationHandler(
            $this->eventRepository,
            $this->ruleParserService,
            $this->jobPollingService,
        );
    }

    public function test_handle_starts_generation_job(): void
    {
        $rule = ['frequency' => 'weekly', 'range' => ['type' => 'count', 'count' => 10]];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $rule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('America/New_York');

        $this->eventRepository->shouldReceive('findById')->with(1)->once()->andReturn($event);

        $this->ruleParserService->shouldReceive('parse')
            ->with($rule, 'America/New_York')
            ->once()
            ->andReturn(collect(range(1, 10)));

        $startResult = new JobPollingResultDTO(
            status: JobStatusEnum::IN_PROGRESS,
            message: 'Job started successfully',
            jobUuid: 'uuid-123',
        );
        $checkedResult = new JobPollingResultDTO(
            status: JobStatusEnum::FINISHED,
            message: 'Job completed successfully',
            jobUuid: 'uuid-123',
        );

        $this->jobPollingService->shouldReceive('startJob')
            ->once()
            ->withArgs(function (string $jobName, array $jobs) {
                return $jobName === 'Generate occurrences for Event #1'
                    && count($jobs) === 1
                    && $jobs[0] instanceof GenerateOccurrencesJob
                    && $jobs[0]->eventId === 1;
            })
            ->andReturn($startResult);

        $this->jobPollingService->shouldReceive('checkJobStatus')
            ->once()
            ->with('uuid-123')
            ->andReturn($checkedResult);

        $result = $this->handler->handle($dto);

        $this->assertSame($checkedResult, $result);
    }

    public function test_handle_throws_validation_exception_when_too_many_occurrences(): void
    {
        $rule = ['frequency' => 'daily', 'range' => ['type' => 'count', 'count' => 2000]];
        $dto = new GenerateOccurrencesDTO(event_id: 1, recurrence_rule: $rule);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');

        $this->eventRepository->shouldReceive('findById')->with(1)->once()->andReturn($event);

        $this->ruleParserService->shouldReceive('parse')
            ->with($rule, 'UTC')
            ->once()
            ->andReturn(collect(range(1, RecurrenceRuleParserService::MAX_OCCURRENCES + 1)));

        $this->jobPollingService->shouldNotReceive('startJob');

        $this->expectException(ValidationException::class);

        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
