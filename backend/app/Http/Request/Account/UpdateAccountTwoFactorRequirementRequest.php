<?php

namespace HiEvents\Http\Request\Account;

use HiEvents\Http\Request\BaseRequest;

class UpdateAccountTwoFactorRequirementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'require_two_factor_authentication' => ['required', 'boolean'],
        ];
    }
}
