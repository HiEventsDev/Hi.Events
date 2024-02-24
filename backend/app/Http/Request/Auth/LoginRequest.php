<?php

namespace HiEvents\Http\Request\Auth;

use HiEvents\Http\Request\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string|min:8',
        ];
    }
}
