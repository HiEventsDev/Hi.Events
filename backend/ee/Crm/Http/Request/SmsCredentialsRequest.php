<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class SmsCredentialsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'account_sid' => ['required', 'string', 'regex:/^AC[0-9a-zA-Z]{32}$/'],
            'auth_token' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_sid.regex' => __('A Twilio Account SID starts with AC and is 34 characters long.'),
        ];
    }
}
