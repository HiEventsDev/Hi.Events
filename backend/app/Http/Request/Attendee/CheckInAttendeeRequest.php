<?php

namespace TicketKitten\Http\Request\Attendee;

use TicketKitten\Http\Request\BaseRequest;

class CheckInAttendeeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:check_in,check_out',
        ];
    }
}
