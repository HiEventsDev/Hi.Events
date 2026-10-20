<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request\Organizer;

use HiEvents\Http\Request\BaseRequest;

class RegisterStripeTerminalReaderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'registration_code' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:100'],
        ];
    }
}
