<?php

namespace HiEvents\Http\Request\Order;

use Illuminate\Foundation\Http\FormRequest;

class RefundOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|gt:0',
            'notify_buyer' => 'required|boolean',
            'cancel_order' => 'required|boolean',
        ];
    }
}
