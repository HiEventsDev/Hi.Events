<?php

namespace HiEvents\Http\Request\Auth\Sso;

use HiEvents\Http\Request\BaseRequest;

class CompleteSsoLoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'login_ticket' => ['required', 'string', 'max:128'],
            'binding' => ['required', 'string', 'max:128'],
            'account_id' => ['required', 'integer'],
        ];
    }
}
