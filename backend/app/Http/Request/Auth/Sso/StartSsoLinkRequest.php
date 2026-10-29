<?php

namespace HiEvents\Http\Request\Auth\Sso;

class StartSsoLinkRequest extends ConfirmIdentityRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'return_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
