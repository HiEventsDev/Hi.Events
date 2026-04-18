<?php

namespace HiEvents\Http\Request\Contact;

use HiEvents\Http\Request\BaseRequest;

class LookupContactByEmailRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
        ];
    }
}
