<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Lineup;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportLineupRequest extends BaseRequest
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    private const MAX_PEOPLE = 500;

    public function rules(): array
    {
        return [
            'people' => ['present', 'array', 'max:'.self::MAX_PEOPLE],
            'people.*.name' => ['required', 'string', 'max:255'],
            'people.*.tagline' => ['nullable', 'string', 'max:255'],
            'people.*.bio' => ['nullable', 'string', 'max:20000'],
            'people.*.links' => ['array', 'max:12'],
            'people.*.links.*' => ['required', 'string', 'url:https', 'max:500'],
            'people.*.billing_tier' => ['nullable', Rule::enum(LineupBillingTier::class)],
            'people.*.role_label' => ['nullable', 'string', 'max:100'],
            'people.*.is_hidden' => ['nullable', 'boolean'],
            'slots' => ['present', 'array', 'max:1000'],
            'slots.*.starts_at' => ['required', 'date_format:'.self::DATETIME_FORMAT],
            'slots.*.ends_at' => ['required', 'date_format:'.self::DATETIME_FORMAT],
            'slots.*.stage' => ['nullable', 'string', 'max:100'],
            'slots.*.title' => ['nullable', 'string', 'max:255'],
            'slots.*.description' => ['nullable', 'string', 'max:20000'],
            'slots.*.people' => ['array', 'max:50'],
            'slots.*.people.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $names = collect($this->input('people', []))->pluck('name')
                ->merge(collect($this->input('slots', []))->pluck('people')->flatten())
                ->map(fn ($name) => Str::lower(trim(preg_replace('/\s+/u', ' ', (string) $name))))
                ->unique();

            if ($names->count() > self::MAX_PEOPLE) {
                $validator->errors()->add('people', __('An import can include up to :max people', ['max' => self::MAX_PEOPLE]));
            }

            foreach ($this->input('slots', []) as $index => $slot) {
                $start = CarbonImmutable::createFromFormat(self::DATETIME_FORMAT, $slot['starts_at']);
                $end = CarbonImmutable::createFromFormat(self::DATETIME_FORMAT, $slot['ends_at']);

                if ($end <= $start) {
                    $validator->errors()->add("slots.$index.ends_at", __('The end time must be after the start time'));
                }

                if (empty($slot['title']) && empty($slot['people'])) {
                    $validator->errors()->add("slots.$index.title", __('Each time slot needs a title or at least one person'));
                }
            }
        });
    }
}
