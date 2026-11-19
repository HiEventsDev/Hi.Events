<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Enums\SmsCountry;
use Illuminate\Validation\Rule;

class SaveSmsConnectionRequest extends SmsCredentialsRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'messaging_service_sid' => ['required', 'string', 'regex:/^MG[0-9a-zA-Z]{32}$/'],
            'enabled_countries' => ['required', 'array', 'min:1', 'max:'.count(SmsCountry::cases())],
            'enabled_countries.*' => ['string', Rule::in(SmsCountry::valuesArray())],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'enabled_countries.required' => __('Choose at least one country to send to.'),
            'enabled_countries.min' => __('Choose at least one country to send to.'),
        ];
    }
}
