<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Account;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAccountRequest extends BaseRequest
{
    public function rules(): array
    {
        $currencies = include __DIR__ . '/../../../../data/currencies.php';

        return [
            'first_name' => RulesHelper::REQUIRED_STRING,
            'last_name' => RulesHelper::STRING,
            'email' => RulesHelper::REQUIRED_EMAIL,
            'password' => ['required', 'confirmed', Password::min(8)],
            'timezone' => ['timezone:all'],
            'currency_code' => [Rule::in(array_values($currencies))],
            'locale' => ['nullable', Rule::in(Locale::getSupportedLocales())],
            'invite_token' => ['nullable', 'string'],
            'marketing_opt_in' => 'boolean|nullable',
            // UTM attribution fields
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'referrer_url' => ['nullable', 'string', 'max:2048'],
            'landing_page' => ['nullable', 'string', 'max:2048'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'fbclid' => ['nullable', 'string', 'max:255'],
            'utm_raw' => ['nullable', 'array'],
        ];
    }
}
