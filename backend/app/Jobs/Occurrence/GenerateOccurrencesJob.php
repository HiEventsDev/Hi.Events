<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Occurrence;

use HiEvents\Exceptions\InvalidRecurrenceRuleException;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateOccurrencesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(
        public readonly int $eventId,
        public readonly array $recurrenceRule,
    ) {
        if (config('queue.occurrences_queue_name') !== null) {
            $this->onQueue(config('queue.occurrences_queue_name'));
        }
    }

    public static function batchName(int $eventId): string
    {
        return "Generate occurrences for Event #$eventId";
    }

    /**
     * @throws Throwable
     */
    public function handle(GenerateOccurrencesFromRuleHandler $handler): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $handler->handle(
                new GenerateOccurrencesDTO(
                    event_id: $this->eventId,
                    recurrence_rule: $this->recurrenceRule,
                )
            );
        } catch (InvalidRecurrenceRuleException $e) {
            $this->fail($e);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('GenerateOccurrencesJob permanently failed after retries', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
        ]);
    }
}
