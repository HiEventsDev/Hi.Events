<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class LookupCashlessWalletRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_public_id' => ['required', 'string', 'max:64'],
        ];
    }
}
