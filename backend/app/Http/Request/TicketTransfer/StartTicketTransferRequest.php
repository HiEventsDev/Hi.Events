<?php

namespace HiEvents\Http\Request\TicketTransfer;

use HiEvents\Http\Request\BaseRequest;

class StartTicketTransferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_email.required' => __('Enter the email address of the person you are giving the ticket to'),
            'recipient_email.email' => __('Email must be a valid email address'),
        ];
    }
}
