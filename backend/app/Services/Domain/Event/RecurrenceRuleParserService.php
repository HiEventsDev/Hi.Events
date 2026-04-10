<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RecurrenceRuleParserService
{
    public const MAX_OCCURRENCES = 1200;

    /**
     * @param array $rule
     * @param string $timezone
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable|null, capacity: int|null, label: string|null}>
     */
    public function parse(array $rule, string $timezone): Collection
    {
        $candidates = collect();

        if (!isset($rule['frequency'])) {
            throw new \InvalidArgumentException(__('Recurrence rule must include a frequency'));
        }

        $frequency = $rule['frequency'];
        $interval = $rule['interval'] ?? 1;
        $rawTimes = $rule['times_of_day'] ?? ['00:00'];
        $fallbackDuration = $rule['duration_minutes'] ?? null;
        $defaultCapacity = $rule['default_capacity'] ?? null;
        $excludedDates = collect($rule['excluded_dates'] ?? []);
        $additionalDates = collect($rule['additional_dates'] ?? []);

        $timeSlots = $this->normalizeTimeSlots($rawTimes, $fallbackDuration);

        $rangeType = $rule['range']['type'] ?? 'count';
        $maxCount = $rangeType === 'count' ? ($rule['range']['count'] ?? 10) : self::MAX_OCCURRENCES;
        $untilDate = $rangeType === 'until'
            ? CarbonImmutable::parse($rule['range']['until'], $timezone)->endOfDay()
            : null;

        $dates = $this->generateDates($rule, $frequency, $interval, $timezone, $maxCount, $untilDate);

        $dates = $dates->reject(function (CarbonImmutable $date) use ($excludedDates) {
            return $excludedDates->contains($date->format('Y-m-d'));
        });

        foreach ($dates as $date) {
            foreach ($timeSlots as $slot) {
                if ($candidates->count() >= self::MAX_OCCURRENCES) {
                    break 2;
                }

                $parts = explode(':', $slot['time']);
                $start = $date->setTime((int) $parts[0], (int) $parts[1], 0);
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
            if ($candidates->count() >= self::MAX_OCCURRENCES) {
                break;
            }

            $addDate = CarbonImmutable::parse($additional['date'], $timezone);
            $parts = explode(':', $additional['time'] ?? '00:00');
            $start = $addDate->setTime((int) $parts[0], (int) $parts[1], 0);
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

        return $candidates->sortBy('start')->values();
    }

    /**
     * @return array<int, array{time: string, label: string|null, duration_minutes: int|null}>
     */
    private function normalizeTimeSlots(array $rawTimes, ?int $fallbackDuration): array
    {
        return array_map(function ($entry) use ($fallbackDuration) {
            if (is_string($entry)) {
                return [
                    'time' => $entry,
                    'label' => null,
                    'duration_minutes' => $fallbackDuration,
                ];
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
            default => collect(),
        };
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
        $timesPerDay = count($rule['times_of_day'] ?? ['00:00']);

        while ($dates->count() * $timesPerDay < $maxCount) {
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
        $timesPerDay = count($rule['times_of_day'] ?? ['00:00']);

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
            ->filter()
            ->sort()
            ->values();

        if ($dayNumbers->isEmpty()) {
            return $dates;
        }

        while ($dates->count() * $timesPerDay < $maxCount) {
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

                if ($dates->count() * $timesPerDay >= $maxCount) {
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
        $daysOfMonth = $rule['days_of_month'] ?? [1];
        $startDate = $this->getStartDate($rule, $timezone);
        $current = $startDate->startOfMonth();
        $timesPerDay = count($rule['times_of_day'] ?? ['00:00']);
        $safetyLimit = $maxCount * 4;
        $iterations = 0;

        while ($dates->count() * $timesPerDay < $maxCount && $iterations < $safetyLimit) {
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

                if ($dates->count() * $timesPerDay >= $maxCount) {
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
        $timesPerDay = count($rule['times_of_day'] ?? ['00:00']);

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

        while ($dates->count() * $timesPerDay < $maxCount && $iterations < $safetyLimit) {
            $iterations++;
            $candidate = $this->getNthDayOfWeekInMonth($current, $carbonDay, $weekPosition);

            if ($candidate !== null && $candidate->greaterThanOrEqualTo($startDate)) {
                if ($untilDate && $candidate->greaterThan($untilDate)) {
                    return $dates;
                }

                $dates->push($candidate);

                if ($dates->count() * $timesPerDay >= $maxCount) {
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
            while ($candidate->dayOfWeekIso !== $carbonDay) {
                $candidate = $candidate->subDay();
            }
            return $candidate->startOfDay();
        }

        $candidate = $firstOfMonth;
        while ($candidate->dayOfWeekIso !== $carbonDay) {
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
        $timesPerDay = count($rule['times_of_day'] ?? ['00:00']);

        $current = $startDate->startOfYear()->month($month);
        $daysInMonth = $current->daysInMonth;
        $current = $current->day(min($dayOfMonth, $daysInMonth));

        if ($current->lessThan($startDate)) {
            $current = $current->addYears($interval);
        }

        while ($dates->count() * $timesPerDay < $maxCount) {
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
