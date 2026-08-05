<?php

namespace Tests\Unit\Services\Domain\Event;

use Carbon\CarbonImmutable;
use HiEvents\Exceptions\InvalidRecurrenceRuleException;
use HiEvents\Services\Domain\Event\RecurrenceRuleParserService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RecurrenceRuleParserServiceTest extends TestCase
{
    private RecurrenceRuleParserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RecurrenceRuleParserService;
    }

    public function test_daily_frequency_generates_correct_dates(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 5,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(5, $result);
        $this->assertEquals('2025-03-01', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-02', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-03', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-04', $result[3]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-05', $result[4]['start']->format('Y-m-d'));
    }

    public function test_daily_frequency_respects_interval(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 3,
            'times_of_day' => ['09:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('2025-03-01', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-04', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-07', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-10', $result[3]['start']->format('Y-m-d'));
    }

    public function test_weekly_frequency_generates_correct_dates(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['monday', 'wednesday', 'friday'],
            'times_of_day' => ['18:00'],
            'range' => [
                'type' => 'count',
                'count' => 6,
                'start' => '2025-03-03',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(6, $result);
        $this->assertEquals('2025-03-03', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-05', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-07', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-10', $result[3]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-12', $result[4]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-14', $result[5]['start']->format('Y-m-d'));
    }

    public function test_weekly_frequency_with_specific_days_of_week(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['tuesday', 'thursday'],
            'times_of_day' => ['12:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-03-04',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('Tuesday', $result[0]['start']->format('l'));
        $this->assertEquals('Thursday', $result[1]['start']->format('l'));
        $this->assertEquals('Tuesday', $result[2]['start']->format('l'));
        $this->assertEquals('Thursday', $result[3]['start']->format('l'));
    }

    public function test_weekly_frequency_every_two_weeks(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 2,
            'days_of_week' => ['monday'],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-03',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-03-03', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-17', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-31', $result[2]['start']->format('Y-m-d'));
    }

    public function test_weekly_frequency_with_empty_days_of_week_returns_empty(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => [],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 5,
                'start' => '2025-03-03',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(0, $result);
    }

    public function test_monthly_by_day_of_month_generates_correct_dates(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_month',
            'days_of_month' => [15],
            'times_of_day' => ['14:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('2025-01-15', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-02-15', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-15', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2025-04-15', $result[3]['start']->format('Y-m-d'));
    }

    public function test_monthly_by_day_of_month_skips_days_that_dont_exist(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_month',
            'days_of_month' => [31],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 5,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $dates = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->toArray();

        $this->assertContains('2025-01-31', $dates);
        $this->assertContains('2025-03-31', $dates);
        $this->assertNotContains('2025-02-31', $dates);
    }

    public function test_monthly_by_day_of_month_with_multiple_days(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_month',
            'days_of_month' => [1, 15],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('2025-03-01', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-15', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-04-01', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2025-04-15', $result[3]['start']->format('Y-m-d'));
    }

    public function test_monthly_by_day_of_month_unsorted_days_match_sorted_days(): void
    {
        $baseRule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_month',
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'until',
                'until' => '2026-11-10',
                'start' => '2026-06-01',
            ],
        ];

        $unsorted = $this->service->parse(array_merge($baseRule, ['days_of_month' => [15, 1]]), 'UTC');
        $sorted = $this->service->parse(array_merge($baseRule, ['days_of_month' => [1, 15]]), 'UTC');

        $unsortedDates = $unsorted->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->toArray();
        $sortedDates = $sorted->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->toArray();

        $this->assertCount(11, $unsorted);
        $this->assertEquals($sortedDates, $unsortedDates);
        $this->assertContains('2026-11-01', $unsortedDates);
        $this->assertNotContains('2026-11-15', $unsortedDates);
    }

    public function test_monthly_by_day_of_week_first_monday(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_week',
            'day_of_week' => 'monday',
            'week_position' => 1,
            'times_of_day' => ['19:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-01-06', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-02-03', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-03', $result[2]['start']->format('Y-m-d'));

        foreach ($result as $occurrence) {
            $this->assertEquals('Monday', $occurrence['start']->format('l'));
        }
    }

    public function test_monthly_by_day_of_week_last_friday(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_week',
            'day_of_week' => 'friday',
            'week_position' => -1,
            'times_of_day' => ['17:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-01-31', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-02-28', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-28', $result[2]['start']->format('Y-m-d'));

        foreach ($result as $occurrence) {
            $this->assertEquals('Friday', $occurrence['start']->format('l'));
        }
    }

    public function test_monthly_by_day_of_week_third_wednesday(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_week',
            'day_of_week' => 'wednesday',
            'week_position' => 3,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-01-15', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-02-19', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-19', $result[2]['start']->format('Y-m-d'));

        foreach ($result as $occurrence) {
            $this->assertEquals('Wednesday', $occurrence['start']->format('l'));
        }
    }

    public function test_yearly_frequency_generates_correct_dates(): void
    {
        $rule = [
            'frequency' => 'yearly',
            'interval' => 1,
            'times_of_day' => ['12:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-06-15',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('2025-06-15', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2026-06-15', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2027-06-15', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2028-06-15', $result[3]['start']->format('Y-m-d'));
    }

    public function test_yearly_frequency_every_two_years(): void
    {
        $rule = [
            'frequency' => 'yearly',
            'interval' => 2,
            'times_of_day' => ['08:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-01-01', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2027-01-01', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2029-01-01', $result[2]['start']->format('Y-m-d'));
    }

    public function test_every_three_months_interval(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 3,
            'monthly_pattern' => 'by_day_of_month',
            'days_of_month' => [1],
            'times_of_day' => ['09:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('2025-01-01', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-04-01', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-07-01', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2025-10-01', $result[3]['start']->format('Y-m-d'));
    }

    public function test_multiple_times_of_day_generates_multiple_occurrences(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['09:00', '14:00', '19:00'],
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(6, $result);

        $this->assertEquals('2025-03-01 09:00', $result[0]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-01 14:00', $result[1]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-01 19:00', $result[2]['start']->format('Y-m-d H:i'));

        $this->assertEquals('2025-03-02 09:00', $result[3]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-02 14:00', $result[4]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-02 19:00', $result[5]['start']->format('Y-m-d H:i'));
    }

    public function test_times_of_day_defaults_to_midnight(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(2, $result);
        $this->assertEquals('00:00', $result[0]['start']->format('H:i'));
        $this->assertEquals('00:00', $result[1]['start']->format('H:i'));
    }

    public function test_duration_minutes_sets_end_date(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'duration_minutes' => 90,
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(2, $result);
        $this->assertEquals('2025-03-01 10:00', $result[0]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-01 11:30', $result[0]['end']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-02 10:00', $result[1]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-02 11:30', $result[1]['end']->format('Y-m-d H:i'));
    }

    public function test_no_duration_minutes_leaves_end_date_null(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['end']);
    }

    public function test_count_limit_stops_after_n_occurrences(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 7,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(7, $result);
    }

    public function test_count_limit_with_multiple_times_per_day(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['09:00', '18:00'],
            'range' => [
                'type' => 'count',
                'count' => 5,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(10, $result);
        $this->assertEquals('2025-03-01 09:00', $result[0]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-01 18:00', $result[1]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-05 09:00', $result[8]['start']->format('Y-m-d H:i'));
        $this->assertEquals('2025-03-05 18:00', $result[9]['start']->format('Y-m-d H:i'));
    }

    public function test_count_limit_with_multiple_times_per_day_weekly(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['monday', 'wednesday', 'friday'],
            'times_of_day' => ['09:00', '18:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-03-03',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(8, $result);
    }

    public function test_count_limit_with_multiple_times_per_day_monthly(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_month',
            'days_of_month' => [1, 15],
            'times_of_day' => ['09:00', '18:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(8, $result);
    }

    public function test_default_count_is_ten_when_not_specified(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(10, $result);
    }

    public function test_until_date_stops_at_specified_date(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'until',
                'until' => '2025-03-05',
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(5, $result);
        $this->assertEquals('2025-03-01', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-05', $result[4]['start']->format('Y-m-d'));
    }

    public function test_until_date_with_weekly_frequency(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['wednesday'],
            'times_of_day' => ['15:00'],
            'range' => [
                'type' => 'until',
                'until' => '2025-03-20',
                'start' => '2025-03-05',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-03-05', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-12', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-19', $result[2]['start']->format('Y-m-d'));
    }

    public function test_excluded_dates_are_skipped(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'excluded_dates' => ['2025-03-03', '2025-03-05'],
            'range' => [
                'type' => 'count',
                'count' => 7,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $dates = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->toArray();

        $this->assertNotContains('2025-03-03', $dates);
        $this->assertNotContains('2025-03-05', $dates);
        $this->assertContains('2025-03-01', $dates);
        $this->assertContains('2025-03-02', $dates);
        $this->assertContains('2025-03-04', $dates);
    }

    public function test_excluded_dates_with_multiple_times_per_day(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['09:00', '18:00'],
            'excluded_dates' => ['2025-03-02'],
            'range' => [
                'type' => 'count',
                'count' => 6,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $dates = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->toArray();

        $this->assertNotContains('2025-03-02', $dates);
    }

    public function test_excluded_occurrences_skip_only_matching_time_slot(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['09:00', '18:00'],
            'excluded_occurrences' => ['2025-03-02 09:00'],
            'range' => [
                'type' => 'count',
                'count' => 6,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $starts = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d H:i'))->toArray();

        $this->assertNotContains('2025-03-02 09:00', $starts);
        $this->assertContains('2025-03-02 18:00', $starts);
    }

    public function test_additional_dates_are_included(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-04-01', 'time' => '20:00'],
                ['date' => '2025-05-15', 'time' => '11:00'],
            ],
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);

        $dates = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->toArray();
        $this->assertContains('2025-04-01', $dates);
        $this->assertContains('2025-05-15', $dates);
    }

    public function test_additional_dates_are_sorted_with_regular_dates(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-03-02', 'time' => '08:00'],
            ],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        for ($i = 1; $i < $result->count(); $i++) {
            $this->assertTrue(
                $result[$i]['start']->greaterThanOrEqualTo($result[$i - 1]['start']),
                'Results should be sorted by start date'
            );
        }
    }

    public function test_excluded_occurrence_removes_matching_additional_date(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-04-01', 'time' => '20:00'],
                ['date' => '2025-05-15', 'time' => '11:00'],
            ],
            'excluded_occurrences' => ['2025-04-01 20:00'],
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $starts = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d H:i'))->toArray();

        $this->assertCount(3, $result);
        $this->assertNotContains('2025-04-01 20:00', $starts);
        $this->assertContains('2025-05-15 11:00', $starts);
        $this->assertContains('2025-03-01 10:00', $starts);
        $this->assertContains('2025-03-02 10:00', $starts);
    }

    public function test_excluded_date_does_not_remove_additional_date_on_same_day(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-04-01', 'time' => '20:00'],
            ],
            'excluded_dates' => ['2025-04-01'],
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $starts = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d H:i'))->toArray();

        $this->assertCount(3, $result);
        $this->assertContains('2025-04-01 20:00', $starts);
    }

    public function test_offset_bearing_additional_date_matches_exclusion_in_event_timezone(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-04-02T02:00:00Z', 'time' => '20:00'],
            ],
            'excluded_occurrences' => ['2025-04-01 20:00'],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'America/New_York');

        $starts = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d H:i'))->toArray();

        $this->assertCount(1, $result);
        $this->assertNotContains('2025-04-02 00:00', $starts);
    }

    public function test_excluded_occurrences_apply_to_rule_slots_and_additional_dates_together(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-04-01', 'time' => '20:00'],
                ['date' => '2025-04-02', 'time' => '20:00'],
            ],
            'excluded_occurrences' => ['2025-03-02 10:00', '2025-04-01 20:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $starts = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d H:i'))->toArray();

        $this->assertEquals(['2025-03-01 10:00', '2025-03-03 10:00', '2025-04-02 20:00'], $starts);
    }

    public function test_additional_dates_default_time_to_midnight(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-06-01'],
            ],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $additionalOccurrence = $result->first(fn ($o) => $o['start']->format('Y-m-d') === '2025-06-01');
        $this->assertNotNull($additionalOccurrence);
        $this->assertEquals('00:00', $additionalOccurrence['start']->format('H:i'));
    }

    public function test_dst_spring_forward_transition(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'duration_minutes' => 60,
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-08',
            ],
        ];

        $result = $this->service->parse($rule, 'America/New_York');

        $this->assertCount(3, $result);

        $this->assertEquals('15:00', $result[0]['start']->format('H:i'));

        $this->assertEquals('14:00', $result[1]['start']->format('H:i'));

        $this->assertEquals('14:00', $result[2]['start']->format('H:i'));
    }

    public function test_weekly_rule_keeps_local_time_across_dst_spring_forward(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['wednesday'],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-05',
            ],
        ];

        $result = $this->service->parse($rule, 'America/New_York');

        $this->assertCount(3, $result);

        $this->assertSame('2025-03-05 15:00', $result[0]['start']->format('Y-m-d H:i'));
        $this->assertSame('2025-03-12 14:00', $result[1]['start']->format('Y-m-d H:i'));
        $this->assertSame('2025-03-19 14:00', $result[2]['start']->format('Y-m-d H:i'));

        foreach ($result as $slot) {
            $this->assertSame('10:00', $slot['start']->setTimezone('America/New_York')->format('H:i'));
        }
    }

    public function test_monthly_rule_keeps_local_time_across_dst_fall_back(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_month',
            'days_of_month' => [1],
            'times_of_day' => ['09:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-10-01',
            ],
        ];

        $result = $this->service->parse($rule, 'America/New_York');

        $this->assertCount(3, $result);

        foreach ($result as $slot) {
            $this->assertSame('09:00', $slot['start']->setTimezone('America/New_York')->format('H:i'));
        }

        $this->assertSame('13:00', $result[0]['start']->format('H:i'));
        $this->assertSame('13:00', $result[1]['start']->format('H:i'));
        $this->assertSame('14:00', $result[2]['start']->format('H:i'));
    }

    public function test_daily_rule_at_skipped_hour_during_spring_forward_does_not_produce_invalid_time(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['02:30'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-08',
            ],
        ];

        $result = $this->service->parse($rule, 'America/New_York');

        $this->assertCount(3, $result);

        $this->assertSame('2025-03-08 07:30', $result[0]['start']->format('Y-m-d H:i'));
        $this->assertSame('2025-03-09 07:30', $result[1]['start']->format('Y-m-d H:i'));
        $this->assertSame('03:30', $result[1]['start']->setTimezone('America/New_York')->format('H:i'));
        $this->assertSame('2025-03-10 06:30', $result[2]['start']->format('Y-m-d H:i'));
    }

    public function test_dst_fall_back_transition(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-11-01',
            ],
        ];

        $result = $this->service->parse($rule, 'America/New_York');

        $this->assertCount(3, $result);

        $this->assertEquals('14:00', $result[0]['start']->format('H:i'));

        $this->assertEquals('15:00', $result[1]['start']->format('H:i'));

        $this->assertEquals('15:00', $result[2]['start']->format('H:i'));
    }

    public function test_timezone_conversion_to_utc(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['20:00'],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'Europe/Berlin');

        $this->assertEquals('19:00', $result[0]['start']->format('H:i'));
        $this->assertEquals('UTC', $result[0]['start']->timezone->getName());
    }

    public function test_cap_at1200_occurrences(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'until',
                'until' => '2030-01-01',
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertLessThanOrEqual(RecurrenceRuleParserService::MAX_OCCURRENCES + 1, $result->count());
    }

    public function test_overflow_pushes_one_extra_candidate_for_handler_detection(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['08:00', '12:00', '16:00', '20:00'],
            'range' => [
                'type' => 'until',
                'until' => '2030-01-01',
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertSame(
            RecurrenceRuleParserService::MAX_OCCURRENCES + 1,
            $result->count(),
        );
    }

    public function test_default_capacity_is_included_in_results(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'default_capacity' => 100,
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(2, $result);
        $this->assertEquals(100, $result[0]['capacity']);
        $this->assertEquals(100, $result[1]['capacity']);
    }

    public function test_default_capacity_is_null_when_not_specified(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertNull($result[0]['capacity']);
    }

    public function test_unknown_frequency_throws(): void
    {
        $rule = [
            'frequency' => 'unknown',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 5,
                'start' => '2025-03-01',
            ],
        ];

        $this->expectException(InvalidRecurrenceRuleException::class);
        $this->expectExceptionMessage('Unsupported recurrence frequency');

        $this->service->parse($rule, 'UTC');
    }

    public function test_result_contains_expected_keys(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'duration_minutes' => 60,
            'default_capacity' => 50,
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertArrayHasKey('start', $result[0]);
        $this->assertArrayHasKey('end', $result[0]);
        $this->assertArrayHasKey('capacity', $result[0]);
        $this->assertInstanceOf(CarbonImmutable::class, $result[0]['start']);
        $this->assertInstanceOf(CarbonImmutable::class, $result[0]['end']);
    }

    public function test_results_are_returned_as_collection(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_additional_dates_respect_duration_minutes(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'duration_minutes' => 120,
            'additional_dates' => [
                ['date' => '2025-06-01', 'time' => '14:00'],
            ],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $additionalOccurrence = $result->first(fn ($o) => $o['start']->format('Y-m-d') === '2025-06-01');
        $this->assertNotNull($additionalOccurrence);
        $this->assertEquals('14:00', $additionalOccurrence['start']->format('H:i'));
        $this->assertEquals('16:00', $additionalOccurrence['end']->format('H:i'));
    }

    public function test_additional_dates_cap_at1200_total(): void
    {
        $additionalDates = [];
        for ($i = 0; $i < 1500; $i++) {
            $date = CarbonImmutable::parse('2030-01-01')->addDays($i);
            $additionalDates[] = ['date' => $date->format('Y-m-d'), 'time' => '10:00'];
        }

        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => $additionalDates,
            'range' => [
                'type' => 'count',
                'count' => 100,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertLessThanOrEqual(RecurrenceRuleParserService::MAX_OCCURRENCES + 1, $result->count());
    }

    public function test_monthly_by_day_of_week_every_two_months(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 2,
            'monthly_pattern' => 'by_day_of_week',
            'day_of_week' => 'tuesday',
            'week_position' => 2,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-01-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-01-14', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-11', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-05-13', $result[2]['start']->format('Y-m-d'));

        foreach ($result as $occurrence) {
            $this->assertEquals('Tuesday', $occurrence['start']->format('l'));
        }
    }

    public function test_daily_with_excluded_dates_still_produces_correct_count(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'excluded_dates' => ['2025-03-02', '2025-03-04'],
            'range' => [
                'type' => 'count',
                'count' => 5,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $dates = $result->pluck('start')->map(fn ($d) => $d->format('Y-m-d'))->toArray();
        $this->assertEquals(['2025-03-01', '2025-03-03', '2025-03-05'], $dates);
    }

    public function test_weekly_with_start_date_mid_week(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['monday', 'friday'],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-03-06',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('2025-03-07', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('Friday', $result[0]['start']->format('l'));
    }

    public function test_duplicate_additional_date_is_deduped(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-03-01', 'time' => '10:00'],
            ],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $startTimes = $result->pluck('start')
            ->map(fn ($d) => $d->toDateTimeString())
            ->toArray();
        $this->assertEquals(count($startTimes), count(array_unique($startTimes)));
    }

    public function test_duplicate_time_slots_are_deduped(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00', '10:00', '14:00'],
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $startTimes = $result->pluck('start')
            ->map(fn ($d) => $d->toDateTimeString())
            ->toArray();
        $this->assertEquals(count($startTimes), count(array_unique($startTimes)));
    }

    public function test_invalid_time_of_day_string_throws(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['25:00'],
            'range' => [
                'type' => 'count',
                'count' => 2,
                'start' => '2025-03-01',
            ],
        ];

        $this->expectException(InvalidRecurrenceRuleException::class);
        $this->service->parse($rule, 'UTC');
    }

    public function test_invalid_time_of_day_object_throws(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => [['time' => '99:99']],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $this->expectException(InvalidRecurrenceRuleException::class);
        $this->service->parse($rule, 'UTC');
    }

    public function test_invalid_additional_date_time_throws(): void
    {
        $rule = [
            'frequency' => 'daily',
            'interval' => 1,
            'times_of_day' => ['10:00'],
            'additional_dates' => [
                ['date' => '2025-04-01', 'time' => 'abc'],
            ],
            'range' => [
                'type' => 'count',
                'count' => 1,
                'start' => '2025-03-01',
            ],
        ];

        $this->expectException(InvalidRecurrenceRuleException::class);
        $this->service->parse($rule, 'UTC');
    }

    public function test_weekly_rule_with_sunday_produces_sunday_dates(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['sunday'],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-03',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-03-09', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-16', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-23', $result[2]['start']->format('Y-m-d'));
        foreach ($result as $occ) {
            $this->assertEquals('Sunday', $occ['start']->format('l'));
        }
    }

    public function test_weekly_rule_with_sunday_mixed_with_other_days(): void
    {
        $rule = [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['sunday', 'wednesday'],
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 4,
                'start' => '2025-03-03',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(4, $result);
        $this->assertEquals('2025-03-05', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-09', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-12', $result[2]['start']->format('Y-m-d'));
        $this->assertEquals('2025-03-16', $result[3]['start']->format('Y-m-d'));
    }

    public function test_monthly_by_day_of_week_first_sunday(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_week',
            'day_of_week' => 'sunday',
            'week_position' => 1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-03-02', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-04-06', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-05-04', $result[2]['start']->format('Y-m-d'));
        foreach ($result as $occ) {
            $this->assertEquals('Sunday', $occ['start']->format('l'));
        }
    }

    public function test_monthly_by_day_of_week_last_sunday(): void
    {
        $rule = [
            'frequency' => 'monthly',
            'interval' => 1,
            'monthly_pattern' => 'by_day_of_week',
            'day_of_week' => 'sunday',
            'week_position' => -1,
            'times_of_day' => ['10:00'],
            'range' => [
                'type' => 'count',
                'count' => 3,
                'start' => '2025-03-01',
            ],
        ];

        $result = $this->service->parse($rule, 'UTC');

        $this->assertCount(3, $result);
        $this->assertEquals('2025-03-30', $result[0]['start']->format('Y-m-d'));
        $this->assertEquals('2025-04-27', $result[1]['start']->format('Y-m-d'));
        $this->assertEquals('2025-05-25', $result[2]['start']->format('Y-m-d'));
        foreach ($result as $occ) {
            $this->assertEquals('Sunday', $occ['start']->format('l'));
        }
    }
}
