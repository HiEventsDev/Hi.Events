<?php

namespace HiEvents\Validators;

use Illuminate\Validation\Rule;

trait EventRules
{
    public function eventRules(): array
    {
        $currencies = include __DIR__ . '/../../data/currencies.php';

        return array_merge($this->minimalRules(), [
            'timezone' => ['timezone:all'],
            'organizer_id' => ['required', 'integer'],
            'currency' => [Rule::in(array_values($currencies))],
            // todo - Revisit the 50k character limit
            'attributes.*.name' => ['string', 'min:1', 'max:50', 'required'],
            'attributes.*.value' => ['min:1', 'max:1000', 'required'],
            'attributes.*.is_public' => ['boolean', 'required'],
            'location_details' => ['array'],
            'location_details.venue_name' => ['string', 'max:100'],
            'location_details.address_line_1' => ['required_with:location_details', 'string', 'max:255'],
            'location_details.address_line_2' => ['string', 'max:255', 'nullable'],
            'location_details.city' => ['required_with:location_details', 'string', 'max:85'],
            'location_details.state_or_region' => ['string', 'max:85'],
            'location_details.zip_or_postal_code' => ['required_with:location_details', 'string', 'max:85'],
            'location_details.country' => ['required_with:location_details', 'string', 'max:2'],
        ]);
    }

    public function minimalRules(): array
    {
        return [
            'title' => ['string', 'required', 'max:150', 'min:1'],
            'description' => ['string', 'min:1', 'max:50000', 'nullable'],
            'start_date' => [
                'date',
                'required',
                Rule::when($this->input('end_date') !== null, ['before_or_equal:end_date'])
            ],
            'end_date' => ['date', 'nullable'],
        ];
    }

    public function eventMessages(): array
    {
        return [
            'title.string' => __('The title field is required'),
            'attributes.*.name.required' => __('The attribute name is required'),
            'attributes.*.value.required' => __('The attribute value is required'),
            'attributes.*.is_public.required' => __('The attribute is_public fields is required'),
            'location_details.address_line_1.required' => __('The address line 1 field is required'),
            'location_details.city.required' => __('The city field is required'),
            'location_details.zip_or_postal_code.required' => __('The zip or postal code field is required'),
            'location_details.country.required' => __('The country field is required'),
            'location_details.country.max' => __('The country field should be a 2 character ISO 3166 code'),
        ];
    }
}
