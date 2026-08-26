<?php

namespace HiEvents\Http\Request\Account;

use Illuminate\Foundation\Http\FormRequest;

class RequestAccountDeletionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'confirmation' => 'required|string|max:255',
            'reason' => 'nullable|string|max:2000',
        ];
    }
}
