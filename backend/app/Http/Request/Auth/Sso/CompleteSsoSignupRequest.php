<?php

namespace HiEvents\Http\Request\Auth\Sso;

use HiEvents\Http\Request\Account\ValidatesAttribution;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use HiEvents\Validators\Rules\PersonNameRule;
use Illuminate\Validation\Rule;

class CompleteSsoSignupRequest extends BaseRequest
{
    use ValidatesAttribution;

    public function rules(): array
    {
        $currencies = include __DIR__.'/../../../../../data/currencies.php';

        return [
            'signup_ticket' => ['required', 'string', 'max:128'],
            'binding' => ['required', 'string', 'max:128'],
            'email_code' => ['nullable', 'string', 'max:16'],
            'first_name' => PersonNameRule::rules(),
            'last_name' => PersonNameRule::rules(required: false),
            'timezone' => ['nullable', 'timezone:all'],
            'currency_code' => ['nullable', Rule::in(array_values($currencies))],
            'locale' => ['nullable', Rule::in(Locale::getSupportedLocales())],
            'invite_token' => ['nullable', 'string'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            ...$this->attributionRules(),
        ];
    }
}
