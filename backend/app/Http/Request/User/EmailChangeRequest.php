<?php

namespace TicketKitten\Http\Request\User;

use TicketKitten\Http\Request\BaseRequest;

class EmailChangeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required|string',
        ];
    }
}
