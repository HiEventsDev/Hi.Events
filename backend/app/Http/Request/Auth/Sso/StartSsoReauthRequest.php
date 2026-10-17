<?php

namespace HiEvents\Http\Request\Auth\Sso;

use HiEvents\Http\Request\BaseRequest;

class StartSsoReauthRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'return_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
