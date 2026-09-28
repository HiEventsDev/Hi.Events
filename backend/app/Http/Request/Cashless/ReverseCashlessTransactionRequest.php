<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class ReverseCashlessTransactionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
