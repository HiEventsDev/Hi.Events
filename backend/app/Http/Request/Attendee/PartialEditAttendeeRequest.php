<?php

namespace TicketKitten\Http\Request\Attendee;

use TicketKitten\DomainObjects\Status\AttendeeStatus;
use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\Rules\InsensitiveIn;

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
