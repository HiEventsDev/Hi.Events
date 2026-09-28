<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateOrganizerTopupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'payment_method' => ['required', Rule::in(CashlessStaffPaymentMethod::valuesArray())],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
