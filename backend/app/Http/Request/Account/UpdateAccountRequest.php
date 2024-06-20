<?php

namespace HiEvents\Http\Request\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function rules(): array
    {
        $currencies = include __DIR__ . '/../../../../data/currencies.php';

        return [
            'name' => 'required|string',
            'timezone' => 'required|timezone:all',
            'currency_code' => [Rule::in(array_values($currencies))],
        ];
    }
}
