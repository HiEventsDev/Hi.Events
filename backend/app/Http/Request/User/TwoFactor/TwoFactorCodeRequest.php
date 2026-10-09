<?php

namespace HiEvents\Http\Request\User\TwoFactor;

use HiEvents\Http\Request\BaseRequest;

class TwoFactorCodeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:16'],
        ];
    }
}
