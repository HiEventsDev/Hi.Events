<?php

namespace HiEvents\Http\Request\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Validators\Rules\PersonNameRule;

class EditAttendeePublicRequest extends BaseRequest
{
    private ?AttendeeDomainObject $attendee = null;

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255', new PersonNameRule($this->attendee()?->getFirstName())],
            'last_name' => ['sometimes', 'string', 'max:255', new PersonNameRule($this->attendee()?->getLastName())],
            'email' => ['sometimes', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.string' => __('First name must be a string'),
            'first_name.max' => __('First name must not exceed 255 characters'),
            'last_name.string' => __('Last name must be a string'),
            'last_name.max' => __('Last name must not exceed 255 characters'),
            'email.email' => __('Email must be a valid email address'),
            'email.max' => __('Email must not exceed 255 characters'),
        ];
    }

    private function attendee(): ?AttendeeDomainObject
    {
        return $this->attendee ??= app(AttendeeRepositoryInterface::class)->findFirstWhere([
            'short_id' => $this->route('attendee_short_id'),
            'event_id' => $this->route('event_id'),
        ]);
    }
}
