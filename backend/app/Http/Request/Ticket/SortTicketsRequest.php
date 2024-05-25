<?php

namespace HiEvents\Http\Request\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class SortTicketsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            '*.id' => 'integer|required',
            '*.order' => 'integer|required',
        ];
    }
}
