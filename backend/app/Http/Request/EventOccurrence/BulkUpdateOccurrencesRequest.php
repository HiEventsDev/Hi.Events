<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class BulkUpdateOccurrencesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(BulkOccurrenceAction::valuesArray())],
            'start_time_shift' => ['nullable', 'integer', 'min:-525600', 'max:525600'],
            'end_time_shift' => ['nullable', 'integer', 'min:-525600', 'max:525600'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'clear_capacity' => ['nullable', 'boolean'],
            'future_only' => ['nullable', 'boolean'],
            'skip_overridden' => ['nullable', 'boolean'],
            'refund_orders' => ['nullable', 'boolean'],
            'apply_to_all' => ['nullable', 'boolean'],
            'occurrence_ids' => ['array'],
            'occurrence_ids.*' => ['integer'],
            'label' => ['nullable', 'string', 'max:255'],
            'clear_label' => ['nullable', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $applyToAll = (bool) $this->input('apply_to_all', false);
            $occurrenceIds = $this->input('occurrence_ids');

            if ($applyToAll) {
                return;
            }

            if (! is_array($occurrenceIds) || count($occurrenceIds) === 0) {
                $validator->errors()->add(
                    'occurrence_ids',
                    __('Specify at least one occurrence_id, or set apply_to_all to true to update every matching occurrence.'),
                );
            }
        });
    }
}
