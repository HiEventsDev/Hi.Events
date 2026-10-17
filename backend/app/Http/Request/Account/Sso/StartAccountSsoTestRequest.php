<?php

namespace HiEvents\Http\Request\Account\Sso;

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
