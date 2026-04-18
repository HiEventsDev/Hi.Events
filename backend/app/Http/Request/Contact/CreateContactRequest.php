<?php

namespace HiEvents\Http\Request\Contact;

use HiEvents\Http\Request\BaseRequest;

class CreateContactRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'attributes' => 'nullable|array',
        ];
    }
}
