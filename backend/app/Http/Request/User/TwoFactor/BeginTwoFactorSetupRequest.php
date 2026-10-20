<?php

namespace HiEvents\Http\Request\User\TwoFactor;

use HiEvents\Http\Request\BaseRequest;

class BeginTwoFactorSetupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required_without:reauth_proof', 'nullable', 'string', 'max:255'],
            'reauth_proof' => ['nullable', 'string', 'max:128'],
        ];
    }
}
