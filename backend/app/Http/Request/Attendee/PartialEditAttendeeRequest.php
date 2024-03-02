<?php

namespace HiEvents\Http\Request\Attendee;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\InsensitiveIn;

class PartialEditAttendeeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', new InsensitiveIn(AttendeeStatus::valuesArray())],
            'first_name' => ['sometimes', 'string', 'max:100', 'min:1'],
            'last_name' => ['sometimes', 'string', 'max:100', 'min:1'],
            'email' => ['sometimes', 'email', 'max:100'],
        ];
    }
}
