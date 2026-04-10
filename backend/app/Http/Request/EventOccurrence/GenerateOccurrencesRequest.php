<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;

class GenerateOccurrencesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'recurrence_rule' => ['required', 'array'],
            'recurrence_rule.frequency' => ['required', 'string', 'in:daily,weekly,monthly,yearly'],
            'recurrence_rule.interval' => ['nullable', 'integer', 'min:1'],
            'recurrence_rule.range' => ['required', 'array'],
            'recurrence_rule.range.type' => ['required', 'string', 'in:count,until'],
            'recurrence_rule.range.count' => ['required_if:recurrence_rule.range.type,count', 'integer', 'min:1', 'max:500'],
            'recurrence_rule.range.until' => ['required_if:recurrence_rule.range.type,until', 'date'],
            'recurrence_rule.range.start' => ['nullable', 'date'],
            'recurrence_rule.days_of_week' => ['required_if:recurrence_rule.frequency,weekly', 'array'],
            'recurrence_rule.days_of_week.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'recurrence_rule.times_of_day' => ['nullable', 'array'],
            'recurrence_rule.times_of_day.*.time' => ['required_if:recurrence_rule.times_of_day.*,array', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'recurrence_rule.times_of_day.*.label' => ['nullable', 'string', 'max:255'],
            'recurrence_rule.times_of_day.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'recurrence_rule.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'recurrence_rule.default_capacity' => ['nullable', 'integer', 'min:0'],
            'recurrence_rule.excluded_dates' => ['nullable', 'array'],
            'recurrence_rule.excluded_dates.*' => ['date'],
            'recurrence_rule.additional_dates' => ['nullable', 'array'],
            'recurrence_rule.additional_dates.*.date' => ['required', 'date'],
            'recurrence_rule.additional_dates.*.time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'recurrence_rule.monthly_pattern' => ['nullable', 'string', 'in:by_day_of_month,by_day_of_week'],
            'recurrence_rule.days_of_month' => ['nullable', 'array'],
            'recurrence_rule.days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'recurrence_rule.day_of_week' => ['nullable', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'recurrence_rule.week_position' => ['nullable', 'integer', 'in:-1,1,2,3,4'],
            'recurrence_rule.month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
