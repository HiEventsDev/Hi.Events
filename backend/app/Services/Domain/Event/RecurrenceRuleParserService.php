<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use HiEvents\Exceptions\InvalidRecurrenceRuleException;
use Illuminate\Support\Collection;

class RecurrenceRuleParserService
{
    public const MAX_OCCURRENCES = 1200;

    /**
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable|null, capacity: int|null, label: string|null}>
     */
    public function parse(array $rule, string $timezone): Collection
    {
        $candidates = collect();

        if (! isset($rule['frequency'])) {
            throw new InvalidRecurrenceRuleException(__('Recurrence rule must include a frequency'));
        }

        $frequency = $rule['frequency'];
        $interval = $rule['interval'] ?? 1;
        $rawTimes = $rule['times_of_day'] ?? ['00:00'];
        $fallbackDuration = $rule['duration_minutes'] ?? null;
        $defaultCapacity = $rule['default_capacity'] ?? null;
        $excludedDates = collect($rule['excluded_dates'] ?? []);
        $excludedOccurrences = collect($rule['excluded_occurrences'] ?? []);
        $additionalDates = collect($rule['additional_dates'] ?? []);

        $timeSlots = $this->normalizeTimeSlots($rawTimes, $fallbackDuration);

        $rangeType = $rule['range']['type'] ?? 'count';
        $maxCount = $rangeType === 'count'
            ? ($rule['range']['count'] ?? 10)
            : self::MAX_OCCURRENCES + 1;
        $untilDate = $rangeType === 'until'
            ? CarbonImmutable::parse($rule['range']['until'], $timezone)->endOfDay()
            : null;

        $dates = $this->generateDates($rule, $frequency, $interval, $timezone, $maxCount, $untilDate);

        foreach ($dates as $date) {
            foreach ($timeSlots as $slot) {
                if ($candidates->count() > self::MAX_OCCURRENCES) {
                    break 2;
                }

                $parts = explode(':', $slot['time']);
                $start = $date->setTime((int) $parts[0], (int) $parts[1], 0);
                if ($this->isExcluded($start, $excludedDates, $excludedOccurrences)) {
                    continue;
                }

                $duration = $slot['duration_minutes'];
                $end = $duration ? $start->addMinutes($duration) : null;

                $startUtc = $start->setTimezone('UTC');
                $endUtc = $end ? $end->setTimezone('UTC') : null;

                $candidates->push([
                    'start' => $startUtc,
                    'end' => $endUtc,
                    'capacity' => $defaultCapacity,
                    'label' => $slot['label'],
                ]);
            }
        }

        foreach ($additionalDates as $additional) {
            if ($candidates->count() > self::MAX_OCCURRENCES) {
                break;
            }

            if (! is_array($additional) || ! isset($additional['date'])) {
                throw new InvalidRecurrenceRuleException(__('Additional recurrence dates must include a date'));
            }

            $addDate = CarbonImmutable::parse($additional['date'], $timezone)->setTimezone($timezone);
            $additionalTime = $additional['time'] ?? '00:00';
            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $additionalTime)) {
                throw new InvalidRecurrenceRuleException(
                    __('Recurrence additional_dates time must be in HH:MM 24-hour format')
                );
            }
            $parts = explode(':', $additionalTime);
            $start = $addDate->setTime((int) $parts[0], (int) $parts[1], 0);
            if ($excludedOccurrences->contains($start->format('Y-m-d H:i'))) {
                continue;
            }

            $end = $fallbackDuration ? $start->addMinutes($fallbackDuration) : null;

            $startUtc = $start->setTimezone('UTC');
            $endUtc = $end ? $end->setTimezone('UTC') : null;

            $candidates->push([
                'start' => $startUtc,
                'end' => $endUtc,
                'capacity' => $defaultCapacity,
                'label' => null,
            ]);
        }

        return $candidates
            ->sortBy(fn (array $candidate) => $candidate['start']->getTimestamp())
            ->unique(fn (array $candidate) => $candidate['start']->toDateTimeString())
            ->values();
    }

    /**
     * @return array<int, array{time: string, label: string|null, duration_minutes: int|null}>
     */
    private function normalizeTimeSlots(array $rawTimes, ?int $fallbackDuration): array
    {
        return array_map(function ($entry) use ($fallbackDuration) {
            $time = is_string($entry) ? $entry : ($entry['time'] ?? null);

            if (! is_string($time) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                throw new InvalidRecurrenceRuleException(
                    __('Recurrence times_of_day entries must be in HH:MM 24-hour format')
                );
            }

            if (is_string($entry)) {
                return [
                    'time' => $entry,
                    'label' => null,
                    'duration_minutes' => $fallbackDuration,
                ];
            }

            if (! is_array($entry) || ! isset($entry['time'])) {
                throw new InvalidRecurrenceRuleException(__('Recurrence time slots must include a time'));
            }

            return [
                'time' => $entry['time'],
                'label' => $entry['label'] ?? null,
                'duration_minutes' => $entry['duration_minutes'] ?? $fallbackDuration,
            ];
        }, $rawTimes);
    }

    private function generateDates(
        array $rule,
        string $frequency,
        int $interval,
        string $timezone,
        int $maxCount,
        ?CarbonImmutable $untilDate,
    ): Collection {
        return match ($frequency) {
            'daily' => $this->generateDailyDates($rule, $interval, $timezone, $maxCount, $untilDate),
            'weekly' => $this->generateWeeklyDates($rule, $interval, $timezone, $maxCount, $untilDate),
            'monthly' => $this->generateMonthlyDates($rule, $interval, $timezone, $maxCount, $untilDate),
            'yearly' => $this->generateYearlyDates($rule, $interval, $timezone, $maxCount, $untilDate),
            default => throw new InvalidRecurrenceRuleException(__('Unsupported recurrence frequency')),
        };
    }

    private function isExcluded(CarbonImmutable $start, Collection $excludedDates, Collection $excludedOccurrences): bool
    {
        return $excludedDates->contains($start->format('Y-m-d'))
            || $excludedOccurrences->contains($start->format('Y-m-d H:i'));
    }

    private function generateDailyDates(
        array $rule,
        int $interval,
        string $timezone,
        int $maxCount,
        ?CarbonImmutable $untilDate,
    ): Collection {
        $dates = collect();
        $startDate = $this->getStartDate($rule, $timezone);
        $current = $startDate;

        while ($dates->count() < $maxCount) {
            if ($untilDate && $current->greaterThan($untilDate)) {
                break;
            }

            $dates->push($current);
            $current = $current->addDays($interval);
        }

        return $dates;
    }

    private function generateWeeklyDates(
        array $rule,
        int $interval,
        string $timezone,
        int $maxCount,
        ?CarbonImmutable $untilDate,
    ): Collection {
        $dates = collect();
        $daysOfWeek = $rule['days_of_week'] ?? [];
        $startDate = $this->getStartDate($rule, $timezone);
        $current = $startDate->startOfWeek(Carbon::MONDAY);

        $dayMap = [
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            'sunday' => Carbon::SUNDAY,
        ];

        $dayNumbers = collect($daysOfWeek)
            ->map(fn (string $day) => $dayMap[strtolower($day)] ?? null)
            ->filter(fn (?int $dayNumber) => $dayNumber !== null)
            ->sort()
            ->values();

        if ($dayNumbers->isEmpty()) {
            return $dates;
        }

        while ($dates->count() < $maxCount) {
            foreach ($dayNumbers as $dayNumber) {
                $daysFromMonday = $dayNumber - CarbonInterface::MONDAY;
                if ($daysFromMonday < 0) {
                    $daysFromMonday += 7;
                }
                $candidate = $current->addDays($daysFromMonday);

                if ($candidate->lessThan($startDate)) {
                    continue;
                }

                if ($untilDate && $candidate->greaterThan($untilDate)) {
                    return $dates;
                }

                $dates->push($candidate);

                if ($dates->count() >= $maxCount) {
                    return $dates;
                }
            }

            $current = $current->addWeeks($interval);
        }

        return $dates;
    }

    private function generateMonthlyDates(
        array $rule,
        int $interval,
        string $timezone,
        int $maxCount,
        ?CarbonImmutable $untilDate,
    ): Collection {
        $pattern = $rule['monthly_pattern'] ?? 'by_day_of_month';

        return match ($pattern) {
            'by_day_of_month' => $this->generateMonthlyByDayOfMonth($rule, $interval, $timezone, $maxCount, $untilDate),
            'by_day_of_week' => $this->generateMonthlyByDayOfWeek($rule, $interval, $timezone, $maxCount, $untilDate),
            default => collect(),
        };
    }

    private function generateMonthlyByDayOfMonth(
        array $rule,
        int $interval,
        string $timezone,
        int $maxCount,
        ?CarbonImmutable $untilDate,
    ): Collection {
        $dates = collect();
        $daysOfMonth = collect($rule['days_of_month'] ?? [1])->sort()->values();
        $startDate = $this->getStartDate($rule, $timezone);
        $current = $startDate->startOfMonth();
        $safetyLimit = $maxCount * 4;
        $iterations = 0;

        while ($dates->count() < $maxCount && $iterations < $safetyLimit) {
            $iterations++;

            foreach ($daysOfMonth as $day) {
                $daysInMonth = $current->daysInMonth;
                if ($day > $daysInMonth) {
                    continue;
                }

                $candidate = $current->setDay($day);

                if ($candidate->lessThan($startDate)) {
                    continue;
                }

                if ($untilDate && $candidate->greaterThan($untilDate)) {
                    return $dates;
                }

                $dates->push($candidate);

                if ($dates->count() >= $maxCount) {
                    return $dates;
                }
            }

            $current = $current->addMonths($interval);
        }

        return $dates;
    }

    private function generateMonthlyByDayOfWeek(
        array $rule,
        int $interval,
        string $timezone,
        int $maxCount,
        ?CarbonImmutable $untilDate,
    ): Collection {
        $dates = collect();
        $dayOfWeek = $rule['day_of_week'] ?? 'monday';
        $weekPosition = $rule['week_position'] ?? 1;
        $startDate = $this->getStartDate($rule, $timezone);
        $current = $startDate->startOfMonth();

        $dayMap = [
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            'sunday' => Carbon::SUNDAY,
        ];

        $carbonDay = $dayMap[strtolower($dayOfWeek)] ?? Carbon::MONDAY;
        $safetyLimit = $maxCount * 4;
        $iterations = 0;

        while ($dates->count() < $maxCount && $iterations < $safetyLimit) {
            $iterations++;
            $candidate = $this->getNthDayOfWeekInMonth($current, $carbonDay, $weekPosition);

            if ($candidate !== null && $candidate->greaterThanOrEqualTo($startDate)) {
                if ($untilDate && $candidate->greaterThan($untilDate)) {
                    return $dates;
                }

                $dates->push($candidate);

                if ($dates->count() >= $maxCount) {
                    return $dates;
                }
            }

            $current = $current->addMonths($interval);
        }

        return $dates;
    }

    private function getNthDayOfWeekInMonth(
        CarbonImmutable $monthStart,
        int $carbonDay,
        int $weekPosition,
    ): ?CarbonImmutable {
        $firstOfMonth = $monthStart->startOfMonth();

        if ($weekPosition === -1) {
            $lastOfMonth = $firstOfMonth->endOfMonth();
            $candidate = $lastOfMonth;
            while ($candidate->dayOfWeek !== $carbonDay) {
                $candidate = $candidate->subDay();
            }

            return $candidate->startOfDay();
        }

        $candidate = $firstOfMonth;
        while ($candidate->dayOfWeek !== $carbonDay) {
            $candidate = $candidate->addDay();
        }

        $candidate = $candidate->addWeeks($weekPosition - 1);

        if ($candidate->month !== $firstOfMonth->month) {
            return null;
        }

        return $candidate->startOfDay();
    }

    private function generateYearlyDates(
        array $rule,
        int $interval,
        string $timezone,
        int $maxCount,
        ?CarbonImmutable $untilDate,
    ): Collection {
        $dates = collect();
        $startDate = $this->getStartDate($rule, $timezone);
        $month = $rule['month'] ?? $startDate->month;
        $dayOfMonth = ($rule['days_of_month'] ?? [$startDate->day])[0] ?? $startDate->day;

        $current = $startDate->startOfYear()->month($month);
        $daysInMonth = $current->daysInMonth;
        $current = $current->day(min($dayOfMonth, $daysInMonth));

        if ($current->lessThan($startDate)) {
            $current = $current->addYears($interval);
        }

        while ($dates->count() < $maxCount) {
            if ($untilDate && $current->greaterThan($untilDate)) {
                break;
            }

            $dates->push($current);
            $nextYear = $current->addYears($interval);
            $daysInTargetMonth = $nextYear->month($month)->daysInMonth;
            $current = $nextYear->month($month)->day(min($dayOfMonth, $daysInTargetMonth));
        }

        return $dates;
    }

    private function getStartDate(array $rule, string $timezone): CarbonImmutable
    {
        if (isset($rule['range']['start'])) {
            return CarbonImmutable::parse($rule['range']['start'], $timezone)->startOfDay();
        }

        return CarbonImmutable::now($timezone)->startOfDay();
    }
}
