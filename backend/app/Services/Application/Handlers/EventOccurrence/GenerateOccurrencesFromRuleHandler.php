<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use HiEvents\Services\Domain\Event\RecurrenceRuleParserService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateOccurrencesFromRuleHandler
{
    public function __construct(
        private readonly EventOccurrenceGeneratorService $generatorService,
        private readonly EventRepositoryInterface        $eventRepository,
        private readonly RecurrenceRuleParserService     $ruleParserService,
        private readonly DatabaseManager                 $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(GenerateOccurrencesDTO $dto): Collection
    {
        $event = $this->eventRepository->findById($dto->event_id);
        $timezone = $event->getTimezone() ?? 'UTC';

        $previewCount = $this->ruleParserService->parse($dto->recurrence_rule, $timezone)->count();

        if ($previewCount >= RecurrenceRuleParserService::MAX_OCCURRENCES) {
            throw ValidationException::withMessages([
                'recurrence_rule' => [
                    __('This rule would generate too many occurrences. Please reduce the date range or frequency, or contact support.'),
                ],
            ]);
        }

        return $this->databaseManager->transaction(function () use ($dto, $event) {
            $this->eventRepository->updateFromArray(
                id: $event->getId(),
                attributes: [
                    EventDomainObjectAbstract::RECURRENCE_RULE => $dto->recurrence_rule,
                    EventDomainObjectAbstract::TYPE => EventType::RECURRING->name,
                ],
            );

            $event->setRecurrenceRule($dto->recurrence_rule);

            return $this->generatorService->generate($event, $dto->recurrence_rule);
        });
    }
}
