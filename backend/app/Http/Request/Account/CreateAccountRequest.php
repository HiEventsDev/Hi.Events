<?php

declare(strict_types=1);

namespace TicketKitten\Http\Request\Account;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\Rules\RulesHelper;

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
        ];
    }
}
