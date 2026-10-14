<?php

namespace HiEvents\Http\Request\Auth;

use HiEvents\Http\Request\BaseRequest;

class TwoFactorLoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
            'account_id' => ['nullable', 'integer'],
            'remember_device' => ['nullable', 'boolean'],
        ];
    }
}
