<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class GenerateOccurrencesFromRuleHandler
{
    public function __construct(
        private readonly EventOccurrenceGeneratorService $generatorService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(GenerateOccurrencesDTO $dto): void
    {
        $this->databaseManager->transaction(function () use ($dto) {
            $this->databaseManager->statement('SELECT pg_advisory_xact_lock(?)', [$dto->event_id]);

            $event = $this->eventRepository->findByIdLocked($dto->event_id);

            $rule = $this->mergeLiveExclusions($dto->recurrence_rule, $event->getRecurrenceRule() ?? []);

            $this->eventRepository->updateFromArray(
                id: $event->getId(),
                attributes: [
                    EventDomainObjectAbstract::RECURRENCE_RULE => $rule,
                    EventDomainObjectAbstract::TYPE => EventType::RECURRING->name,
                ],
            );

            $event->setRecurrenceRule($rule);

            $this->generatorService->generate($event, $rule);
        });
    }

    private function mergeLiveExclusions(array $submittedRule, array $liveRule): array
    {
        foreach (['excluded_occurrences', 'excluded_dates', 'additional_dates'] as $key) {
            $merged = array_values(array_unique(array_merge(
                $liveRule[$key] ?? [],
                $submittedRule[$key] ?? [],
            ), SORT_REGULAR));

            if ($merged !== []) {
                $submittedRule[$key] = $merged;
            }
        }

        return $submittedRule;
    }
}
