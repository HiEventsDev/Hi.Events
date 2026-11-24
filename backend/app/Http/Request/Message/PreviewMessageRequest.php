<?php

namespace HiEvents\Http\Request\Message;

use Illuminate\Foundation\Http\FormRequest;

class PreviewMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:20000',
        ];
    }
}
