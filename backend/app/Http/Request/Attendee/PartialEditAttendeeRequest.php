<?php

namespace HiEvents\Http\Request\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Validators\Rules\InsensitiveIn;
use HiEvents\Validators\Rules\PersonNameRule;

class PartialEditAttendeeRequest extends BaseRequest
{
    private ?AttendeeDomainObject $attendee = null;

    public function rules(): array
    {
        return [
            'status' => ['sometimes', new InsensitiveIn(AttendeeStatus::valuesArray())],
            'first_name' => ['sometimes', 'string', 'max:100', 'min:1', new PersonNameRule($this->attendee()?->getFirstName())],
            'last_name' => ['sometimes', 'string', 'max:100', 'min:1', new PersonNameRule($this->attendee()?->getLastName())],
            'email' => ['sometimes', 'email', 'max:100'],
        ];
    }

    private function attendee(): ?AttendeeDomainObject
    {
        return $this->attendee ??= app(AttendeeRepositoryInterface::class)->findFirstWhere([
            'id' => $this->route('attendee_id'),
            'event_id' => $this->route('event_id'),
        ]);
    }
}
