<?php

namespace HiEvents\Http\Request\User\TwoFactor;

use HiEvents\Http\Request\BaseRequest;

class BeginTwoFactorSetupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
