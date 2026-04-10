<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\Http\Request\BaseRequest;
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
            'occurrence_ids' => ['nullable', 'array'],
            'occurrence_ids.*' => ['integer'],
            'label' => ['nullable', 'string', 'max:255'],
            'clear_label' => ['nullable', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
