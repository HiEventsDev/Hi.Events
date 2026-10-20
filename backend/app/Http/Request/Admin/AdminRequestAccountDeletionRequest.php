<?php

namespace HiEvents\Http\Request\Admin;

use HiEvents\Http\Request\BaseRequest;

class AdminRequestAccountDeletionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'execute_immediately' => ['sometimes', 'boolean'],
        ];
    }
}
