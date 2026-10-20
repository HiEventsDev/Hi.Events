<?php

namespace HiEvents\Validators;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\LocationType;
use Illuminate\Validation\Rule;

trait EventRules
{
    public function eventRules(): array
    {
        $currencies = include __DIR__.'/../../data/currencies.php';

        return array_merge($this->minimalRules(), [
            'type' => ['nullable', Rule::in(EventType::valuesArray())],
            'timezone' => ['timezone:all'],
            'organizer_id' => ['required', 'integer'],
            'currency' => [Rule::in(array_values($currencies))],
            'category' => ['nullable', Rule::in(EventCategory::valuesArray())],
            // todo - Revisit the 50k character limit
            'attributes.*.name' => ['string', 'min:1', 'max:50', 'required'],
            'attributes.*.value' => ['min:1', 'max:1000', 'required'],
            'attributes.*.is_public' => ['boolean', 'required'],
            'event_location' => ['nullable', 'array'],
            'event_location.type' => ['required_with:event_location', Rule::in(LocationType::valuesArray())],
            'event_location.location_id' => [
                'nullable', 'integer',
                'required_if:event_location.type,'.LocationType::IN_PERSON->name,
            ],
            'event_location.online_event_connection_details' => [
                'nullable', 'string', 'max:10000',
                'required_if:event_location.type,'.LocationType::ONLINE->name,
            ],
        ]);
    }

    public function minimalRules(): array
    {
        $isRecurring = $this->input('type') === EventType::RECURRING->name;

        return [
            'title' => ['string', 'required', 'max:150', 'min:1'],
            'description' => ['string', 'min:1', 'max:50000', 'nullable'],
            'start_date' => [
                'date',
                $isRecurring ? 'nullable' : 'required',
                Rule::when($this->input('end_date') !== null, ['before_or_equal:end_date']),
            ],
            'end_date' => ['date', 'nullable'],
        ];
    }

    public function eventMessages(): array
    {
        return [
            'title.string' => __('The title field is required'),
            'attributes.*.name.required' => __('The attribute name is required'),
            'attributes.*.value.required' => __('The attribute value is required'),
            'attributes.*.is_public.required' => __('The attribute is_public fields is required'),
            'event_location.location_id.required_if' => __('A saved location must be selected for in-person events'),
        ];
    }
}
