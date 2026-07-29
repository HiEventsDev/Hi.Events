<?php

namespace Tests\Unit\Jobs\Occurrence;

use HiEvents\Exceptions\InvalidRecurrenceRuleException;
use HiEvents\Jobs\Occurrence\GenerateOccurrencesJob;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use Illuminate\Bus\Batch;
use Mockery;
use Tests\TestCase;

class GenerateOccurrencesJobTest extends TestCase
{
    public function test_handle_delegates_to_handler(): void
    {
        $rule = ['frequency' => 'weekly'];
        $job = new GenerateOccurrencesJob(1, $rule);

        $handler = Mockery::mock(GenerateOccurrencesFromRuleHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->withArgs(function (GenerateOccurrencesDTO $dto) use ($rule) {
                return $dto->event_id === 1 && $dto->recurrence_rule === $rule;
            });

        $job->handle($handler);
    }

    public function test_handle_fails_immediately_on_invalid_rule(): void
    {
        $job = Mockery::mock(GenerateOccurrencesJob::class.'[fail]', [1, ['frequency' => 'weekly']]);
        $job->shouldReceive('fail')->once()->with(Mockery::type(InvalidRecurrenceRuleException::class));

        $handler = Mockery::mock(GenerateOccurrencesFromRuleHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->andThrow(new InvalidRecurrenceRuleException('bad rule'));

        $job->handle($handler);
    }

    public function test_handle_bails_when_batch_is_cancelled(): void
    {
        $batch = Mockery::mock(Batch::class);
        $batch->shouldReceive('cancelled')->andReturn(true);

        $job = Mockery::mock(GenerateOccurrencesJob::class.'[batch]', [1, ['frequency' => 'weekly']]);
        $job->shouldReceive('batch')->andReturn($batch);

        $handler = Mockery::mock(GenerateOccurrencesFromRuleHandler::class);
        $handler->shouldNotReceive('handle');

        $job->handle($handler);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
