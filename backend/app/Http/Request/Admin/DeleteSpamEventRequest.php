<?php

namespace HiEvents\Http\Request\Admin;

use HiEvents\Http\Request\BaseRequest;

class DeleteSpamEventRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'delete_account' => ['sometimes', 'boolean'],
        ];
    }
}
