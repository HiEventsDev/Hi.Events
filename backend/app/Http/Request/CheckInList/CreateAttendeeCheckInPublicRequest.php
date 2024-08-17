<?php

namespace HiEvents\Http\Request\CheckInList;

use HiEvents\Http\Request\BaseRequest;

class CreateAttendeeCheckInPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_public_ids' => ['required', 'array'],
            'attendee_public_ids.*' => ['required', 'string'],
        ];
    }
}
