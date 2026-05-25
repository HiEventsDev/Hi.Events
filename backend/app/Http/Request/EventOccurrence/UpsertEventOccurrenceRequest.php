<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertEventOccurrenceRequest extends BaseRequest
{
    /**
     * Status is intentionally absent — lifecycle transitions live in their
     * own actions so the cancel/reactivate side effects (refund handling,
     * recurrence exclusions, notifications) always fire.
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
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
            'clear_event_location' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_location.location_id.required_if' => __('A saved location must be selected for in-person occurrences'),
        ];
    }
}
