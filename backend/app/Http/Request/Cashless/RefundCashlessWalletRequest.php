<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\DomainObjects\Enums\CashlessRefundMethod;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class RefundCashlessWalletRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(CashlessRefundMethod::valuesArray())],
        ];
    }
}
