<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class CreateCashlessSalesPointSessionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'pin' => ['nullable', 'string', 'max:32'],
        ];
    }
}
