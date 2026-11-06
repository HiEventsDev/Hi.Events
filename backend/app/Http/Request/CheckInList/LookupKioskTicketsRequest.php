<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\CheckInList;

use HiEvents\Http\Request\BaseRequest;

class LookupKioskTicketsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'last_name' => ['required', 'string', 'max:100'],
        ];
    }
}
