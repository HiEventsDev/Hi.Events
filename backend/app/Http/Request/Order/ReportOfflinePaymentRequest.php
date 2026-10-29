<?php

namespace HiEvents\Http\Request\Order;

use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportOfflinePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => [
                'nullable',
                'string',
                'max:100',
                'not_regex:/[\[\]()<>\\\\]/',
                'not_regex:/(https?:|www\.)/i',
                'not_regex:'.PersonNameRule::LINKABLE_DOMAIN_PATTERN,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'note.not_regex' => __('The note can only contain letters, numbers, spaces and basic punctuation.'),
        ];
    }
}
