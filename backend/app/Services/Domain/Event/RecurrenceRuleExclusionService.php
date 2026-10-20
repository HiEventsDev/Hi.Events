<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class RecurrenceRuleExclusionService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @param  array<int, string>  $startDates  UTC datetime strings of cancelled occurrences
     */
    public function addExclusions(int $eventId, array $startDates): void
    {
        $event = $this->eventRepository->findByIdLocked($eventId);

        if ($event->getType() !== EventType::RECURRING->name) {
            return;
        }

        $rule = $this->extractRule($event);
        $excluded = $rule['excluded_occurrences'] ?? [];
        $changed = false;

        foreach (array_unique($startDates) as $startDate) {
            $datetime = $this->formatExclusion($startDate, $event->getTimezone() ?? 'UTC');

            if (! in_array($datetime, $excluded, true)) {
                $excluded[] = $datetime;
                $changed = true;
            }
        }

        if (! $changed) {
            return;
        }

        $rule['excluded_occurrences'] = $excluded;

        $this->eventRepository->updateFromArray(
            id: $eventId,
            attributes: [
                EventDomainObjectAbstract::RECURRENCE_RULE => $rule,
            ],
        );
    }

    public function removeExclusion(int $eventId, string $startDate): void
    {
        $event = $this->eventRepository->findByIdLocked($eventId);

        if ($event->getType() !== EventType::RECURRING->name) {
            return;
        }

        $rule = $this->extractRule($event);
        $datetime = $this->formatExclusion($startDate, $event->getTimezone() ?? 'UTC');
        $legacyDate = substr($datetime, 0, 10);

        $excludedOccurrences = $rule['excluded_occurrences'] ?? [];
        $excludedDates = $rule['excluded_dates'] ?? [];

        if (! in_array($datetime, $excludedOccurrences, true)
            && ! in_array($legacyDate, $excludedDates, true)
        ) {
            return;
        }

        $rule['excluded_occurrences'] = array_values(array_filter(
            $excludedOccurrences,
            static fn (string $dt) => $dt !== $datetime,
        ));
        $rule['excluded_dates'] = array_values(array_filter(
            $excludedDates,
            static fn (string $d) => $d !== $legacyDate,
        ));

        $this->eventRepository->updateFromArray(
            id: $eventId,
            attributes: [
                EventDomainObjectAbstract::RECURRENCE_RULE => $rule,
            ],
        );
    }

    private function extractRule(EventDomainObject $event): array
    {
        $rule = $event->getRecurrenceRule() ?? [];

        if (is_string($rule)) {
            $rule = json_decode($rule, true, 512, JSON_THROW_ON_ERROR);
        }

        return $rule;
    }

    private function formatExclusion(string $startDate, string $timezone): string
    {
        return CarbonImmutable::parse($startDate, 'UTC')
            ->setTimezone($timezone)
            ->format('Y-m-d H:i');
    }
}
