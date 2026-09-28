<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateCashlessWalletStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([CashlessWalletStatus::ACTIVE->value, CashlessWalletStatus::FROZEN->value])],
        ];
    }
}
