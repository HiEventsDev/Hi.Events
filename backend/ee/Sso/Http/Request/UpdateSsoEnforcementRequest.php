<?php

namespace HiEvents\Enterprise\Sso\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class UpdateSsoEnforcementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'exempt_account_user_ids' => ['nullable', 'array', 'max:1000'],
            'exempt_account_user_ids.*' => ['integer'],
        ];
    }

    /**
     * @return int[]
     */
    public function exemptAccountUserIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('exempt_account_user_ids') ?? [])));
    }
}
