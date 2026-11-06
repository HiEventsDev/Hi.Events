<?php

namespace HiEvents\Http\Request\OAuth;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class ApproveOAuthAuthorizationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'access' => ['required', Rule::enum(ApiKeyAccess::class)],
            'password' => ['nullable', 'string', 'max:255'],
            'reauth_proof' => ['nullable', 'string', 'max:128'],
        ];
    }
}
