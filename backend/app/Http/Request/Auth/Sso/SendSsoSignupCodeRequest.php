<?php

namespace HiEvents\Http\Request\Auth\Sso;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;

class SendSsoSignupCodeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'signup_ticket' => ['required', 'string', 'max:128'],
            'binding' => ['required', 'string', 'max:128'],
            'locale' => ['nullable', 'in:'.implode(',', Locale::getSupportedLocales())],
        ];
    }
}
