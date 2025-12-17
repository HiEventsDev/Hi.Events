<?php

namespace HiEvents\Http\Request\SelfService;

use HiEvents\Http\Request\BaseRequest;

class EditAttendeePublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
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
}
