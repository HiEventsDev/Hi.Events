<?php

namespace HiEvents\Http\Request\Organizer;

use Illuminate\Validation\Rule;
use HiEvents\Http\Request\BaseRequest;

class UpsertOrganizerRequest extends BaseRequest
{
    public function rules(): array
    {
        $currencies = include __DIR__ . '/../../../../data/currencies.php';

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['email', 'required'],
            'phone' => ['string'],
            'website' => ['url'],
            'description' => ['string'],
            'timezone' => ['timezone', 'required'],
            'currency' => ['required', Rule::in(array_values($currencies))],
            'logo' => [
                'image',
                'nullable',
                'max:2548',
                'dimensions:min_width=200,min_height=200,max_width=2500,max_height=2500'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.dimensions' => 'The logo must be at least 200x200 pixels and at most 2500x2500 pixels.',
            'logo.max' => 'The logo may not be larger than 2.5MB.',
        ];
    }
}
