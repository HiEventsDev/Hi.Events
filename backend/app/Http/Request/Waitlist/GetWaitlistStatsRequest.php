<?php

namespace HiEvents\Http\Request\Waitlist;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class GetWaitlistStatsRequest extends BaseRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id');

        return [
            'event_occurrence_id' => [
                'nullable',
                'integer',
                Rule::exists('event_occurrences', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
