<?php

namespace HiEvents\Enterprise\Sso\Http\Request;

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
