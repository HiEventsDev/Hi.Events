<?php

namespace HiEvents\Http\Request\Account\Sso;

use HiEvents\Http\Request\BaseRequest;

class AddAccountSsoDomainRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:253'],
        ];
    }
}
