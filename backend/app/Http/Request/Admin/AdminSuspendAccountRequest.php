<?php

namespace HiEvents\Http\Request\Admin;

use HiEvents\Http\Request\BaseRequest;

class AdminSuspendAccountRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'notify_account_owner' => ['sometimes', 'boolean'],
        ];
    }
}
