<?php

namespace HiEvents\Http\Request\Auth\Sso;

use HiEvents\Http\Request\BaseRequest;

class ExchangeSsoCodeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:4096'],
            'state' => ['required', 'string', 'max:128'],
            'binding' => ['required', 'string', 'max:128'],
        ];
    }
}
