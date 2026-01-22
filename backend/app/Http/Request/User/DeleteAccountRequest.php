<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\User;

use HiEvents\Http\Request\BaseRequest;

class DeleteAccountRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'confirmation' => 'required|string|min:3',
            'password' => 'required|string|min:8',
        ];
    }
}
