<?php

namespace HiEvents\Http\Request\Account\Sso;

use HiEvents\Http\Request\BaseRequest;

class UpdateSsoMemberEnforcementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'sso_enforced' => ['required', 'boolean'],
        ];
    }
}
