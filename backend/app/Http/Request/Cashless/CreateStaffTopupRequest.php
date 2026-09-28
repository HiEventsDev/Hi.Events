<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateStaffTopupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_public_id' => ['required', 'string', 'max:64'],
            'client_reference_id' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'payment_method' => ['required', Rule::in(CashlessStaffPaymentMethod::valuesArray())],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
