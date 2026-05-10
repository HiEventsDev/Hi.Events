<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;

class UpsertEventOccurrenceRequest extends BaseRequest
{
    /**
     * Status is intentionally absent from this request. Lifecycle transitions
     * (cancel / reactivate) live in their own actions because they fan out
     * into attendee cancellation, refund handling, recurrence exclusions and
     * notification dispatch — none of which the generic upsert handler does.
     * Allowing `status` here would let an API caller flip an occurrence to
     * CANCELLED via update without firing any of those side effects.
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
