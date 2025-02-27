<?php

namespace HiEvents\Http\Request\CheckInList;

use HiEvents\DomainObjects\Enums\AttendeeCheckInActionType;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateAttendeeCheckInPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendees' => ['required', 'array'],
            'attendees.*.public_id' => ['required', 'string'],
            'attendees.*.action' => ['required', 'string', Rule::in(AttendeeCheckInActionType::valuesArray())],
        ];
    }
}
