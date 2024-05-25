<?php

namespace HiEvents\Http\Request\User;

use HiEvents\Http\Request\BaseRequest;

class EmailChangeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required|string',
        ];
    }
}
