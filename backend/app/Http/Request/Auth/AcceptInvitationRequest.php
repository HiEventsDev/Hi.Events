<?php

namespace HiEvents\Http\Request\Auth;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\PersonNameRule;

class AcceptInvitationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => PersonNameRule::rules(),
            'last_name' => PersonNameRule::rules(required: false),
            'password' => 'required|string|min:8|confirmed',
            'timezone' => ['required', 'timezone:all'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ];
    }
}
