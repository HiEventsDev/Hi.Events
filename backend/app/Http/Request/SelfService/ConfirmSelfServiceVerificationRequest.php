<?php

namespace HiEvents\Http\Request\SelfService;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;

class ConfirmSelfServiceVerificationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:'.SelfServiceVerificationService::CODE_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('Enter the code from the email'),
            'code.digits' => __('The code is :digits digits long'),
        ];
    }
}
