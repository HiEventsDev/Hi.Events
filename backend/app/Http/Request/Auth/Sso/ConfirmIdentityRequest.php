<?php

namespace HiEvents\Http\Request\Auth\Sso;

use HiEvents\Http\Request\BaseRequest;

class ConfirmIdentityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'max:255'],
            'reauth_proof' => ['nullable', 'string', 'max:128'],
        ];
    }
}
