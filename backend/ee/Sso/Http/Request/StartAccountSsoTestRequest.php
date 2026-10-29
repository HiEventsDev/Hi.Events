<?php

namespace HiEvents\Enterprise\Sso\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class StartAccountSsoTestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'return_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
