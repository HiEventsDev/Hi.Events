<?php

namespace HiEvents\Http\Request\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class StartSsoRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::in([SsoIntent::LOGIN->value, SsoIntent::SIGNUP->value, SsoIntent::INVITE->value])],
            'return_path' => ['nullable', 'string', 'max:500'],
            'invitation_token' => ['required_if:intent,'.SsoIntent::INVITE->value, 'nullable', 'string', 'max:2000'],
            'timezone' => ['nullable', 'timezone:all'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'switch_account' => ['nullable', 'boolean'],
            'login_hint' => ['nullable', 'string', 'email', 'max:255'],
            'silent' => ['nullable', 'boolean'],
        ];
    }
}
