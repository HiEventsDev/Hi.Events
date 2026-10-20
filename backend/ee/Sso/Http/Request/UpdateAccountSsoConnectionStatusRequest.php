<?php

namespace HiEvents\Enterprise\Sso\Http\Request;

use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSsoConnectionStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(AccountSsoConnectionStatus::valuesArray())],
        ];
    }
}
