<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class CreateContactNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
