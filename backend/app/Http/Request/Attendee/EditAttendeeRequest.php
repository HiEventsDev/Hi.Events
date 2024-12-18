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
            'product_id' => RulesHelper::REQUIRED_NUMERIC,
            'product_price_id' => RulesHelper::REQUIRED_NUMERIC,
            'notes' => RulesHelper::OPTIONAL_TEXT_MEDIUM_LENGTH,
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('Email is required'),
            'email.email' => __('Email must be a valid email address'),
            'first_name.required' => __('First name is required'),
            'last_name.required' => __('Last name is required'),
            'product_id.required' => __('Product is required'),
            'product_price_id.required' => __('Product price is required'),
            'product_id.numeric' => '',
            'product_price_id.numeric' => '',
            'notes.max' => __('Notes must be less than 2000 characters'),
        ];
    }
}
