<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Validation\Rule;

class SubmitSignupFormRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'first_name' => PersonNameRule::rules(required: false),
            'last_name' => PersonNameRule::rules(required: false),
            'locale' => ['nullable', Rule::in(Locale::getSupportedLocales())],
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
