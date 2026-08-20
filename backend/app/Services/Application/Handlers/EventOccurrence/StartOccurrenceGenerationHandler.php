<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\Jobs\Occurrence\GenerateOccurrencesJob;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Domain\Event\RecurrenceRuleParserService;
use HiEvents\Services\Infrastructure\Jobs\DTO\JobPollingResultDTO;
use HiEvents\Services\Infrastructure\Jobs\JobPollingService;
use Illuminate\Validation\ValidationException;

class StartOccurrenceGenerationHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly RecurrenceRuleParserService $ruleParserService,
        private readonly JobPollingService $jobPollingService,
    ) {}

    public function handle(GenerateOccurrencesDTO $dto): JobPollingResultDTO
    {
        $event = $this->eventRepository->findById($dto->event_id);
        $timezone = $event->getTimezone() ?? 'UTC';

        $previewCount = $this->ruleParserService->parse($dto->recurrence_rule, $timezone)->count();

        if ($previewCount > RecurrenceRuleParserService::MAX_OCCURRENCES) {
            throw ValidationException::withMessages([
                'recurrence_rule' => [
                    __('This rule would generate too many occurrences. Please reduce the date range or frequency, or contact support.'),
                ],
            ]);
        }

        $startResult = $this->jobPollingService->startJob(
            jobName: GenerateOccurrencesJob::batchName($dto->event_id),
            jobs: [new GenerateOccurrencesJob($dto->event_id, $dto->recurrence_rule)],
        );

        return $this->jobPollingService->checkJobStatus($startResult->jobUuid);
    }
}
