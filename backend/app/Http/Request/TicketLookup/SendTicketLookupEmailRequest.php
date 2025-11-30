<?php

namespace HiEvents\Http\Request\TicketLookup;

use Illuminate\Foundation\Http\FormRequest;

class SendTicketLookupEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }
}
