<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class CreateCashlessTopupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ];
    }
}
