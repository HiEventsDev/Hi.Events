<?php

namespace HiEvents\Http\Request\Account\Sso;

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
