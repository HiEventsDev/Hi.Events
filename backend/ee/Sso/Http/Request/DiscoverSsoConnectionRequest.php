<?php

namespace HiEvents\Enterprise\Sso\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class DiscoverSsoConnectionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
