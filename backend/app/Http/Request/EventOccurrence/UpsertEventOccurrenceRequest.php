<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertEventOccurrenceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'status' => ['nullable', Rule::in(EventOccurrenceStatus::valuesArray())],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
