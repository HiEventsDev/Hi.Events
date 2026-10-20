<?php

namespace HiEvents\Http\Request\User\TwoFactor;

use HiEvents\Http\Request\BaseRequest;

class DisableTwoFactorRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required_without:reauth_proof', 'nullable', 'string', 'max:255'],
            'reauth_proof' => ['nullable', 'string', 'max:128'],
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'max:16'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:32'],
        ];
    }
}
