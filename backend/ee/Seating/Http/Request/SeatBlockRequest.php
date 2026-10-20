<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class SeatBlockRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'all_upcoming_dates' => ['sometimes', 'boolean'],
            'event_occurrence_ids' => ['required_unless:all_upcoming_dates,true', 'prohibited_if:all_upcoming_dates,true', 'array', 'min:1', 'max:1200'],
            'event_occurrence_ids.*' => ['required', 'integer'],
            'seat_uids' => ['required', 'array', 'min:1', 'max:500'],
            'seat_uids.*' => ['required', 'string', 'max:24'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
