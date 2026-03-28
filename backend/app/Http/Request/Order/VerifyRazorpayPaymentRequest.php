<?php

namespace HiEvents\Http\Request\Order;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRazorpayPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'razorpay_payment_id' => [
                'required',
                'string',
                'regex:/^pay_[a-zA-Z0-9]+$/',
            ],
            'razorpay_order_id' => [
                'required',
                'string',
                'regex:/^order_[a-zA-Z0-9]+$/',
            ],
            'razorpay_signature' => [
                'required',
                'string',
                'regex:/^[a-f0-9]{64}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'razorpay_payment_id.required' => __('The Razorpay payment ID is required.'),
            'razorpay_payment_id.regex' => __('The Razorpay payment ID must start with "pay_" and contain only alphanumeric characters.'),
            'razorpay_order_id.required' => __('The Razorpay order ID is required.'),
            'razorpay_order_id.regex' => __('The Razorpay order ID must start with "order_" and contain only alphanumeric characters.'),
            'razorpay_signature.required' => __('The Razorpay signature is required.'),
            'razorpay_signature.regex' => __('The Razorpay signature format is invalid (must be a 64-character hex string).'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'razorpay_payment_id' => trim($this->razorpay_payment_id),
            'razorpay_order_id' => trim($this->razorpay_order_id),
            'razorpay_signature' => trim($this->razorpay_signature),
        ]);
    }
}