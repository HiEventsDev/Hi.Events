<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class ResendBoxOfficeOrderConfirmationPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
