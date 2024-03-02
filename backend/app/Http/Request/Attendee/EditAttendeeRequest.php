<?php

namespace HiEvents\Http\Request\Attendee;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;

class EditAttendeeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => RulesHelper::REQUIRED_EMAIL,
            'first_name' => RulesHelper::REQUIRED_STRING,
            'last_name' => RulesHelper::REQUIRED_STRING,
            'ticket_id' => RulesHelper::REQUIRED_NUMERIC,
            'ticket_price_id' => RulesHelper::REQUIRED_NUMERIC,
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('Email is required'),
            'email.email' => __('Email must be a valid email address'),
            'first_name.required' => __('First name is required'),
            'last_name.required' => __('Last name is required'),
            'ticket_id.required' => __('Ticket is required'),
            'ticket_price_id.required' => __('Ticket price is required'),
            'ticket_id.numeric' => '',
            'ticket_price_id.numeric' => '',
        ];
    }
}
