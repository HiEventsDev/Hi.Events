<?php

namespace HiEvents\Http\Request\Report;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class GetReportRequest extends BaseRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id') ?? $this->route('eventId');

        return [
            'start_date' => 'date|before:end_date|required_with:end_date|nullable',
            'end_date' => 'date|after:start_date|required_with:start_date|nullable',
            'occurrence_id' => [
                'integer',
                'nullable',
                Rule::exists('event_occurrences', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
