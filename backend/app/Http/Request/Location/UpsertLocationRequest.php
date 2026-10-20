<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Location;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Services\Infrastructure\Geo\GooglePlacesGeoProvider;
use Illuminate\Validation\Rule;

class UpsertLocationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'structured_address' => ['required', 'array'],
            'structured_address.venue_name' => ['nullable', 'string', 'max:255'],
            'structured_address.address_line_1' => ['nullable', 'string', 'max:255'],
            'structured_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'structured_address.city' => ['nullable', 'string', 'max:85'],
            'structured_address.state_or_region' => ['nullable', 'string', 'max:85'],
            'structured_address.zip_or_postal_code' => ['nullable', 'string', 'max:85'],
            'structured_address.country' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'provider' => [
                'nullable',
                'string',
                Rule::in([GooglePlacesGeoProvider::PROVIDER_NAME]),
                'required_with:provider_place_id',
            ],
            'provider_place_id' => [
                'nullable',
                'string',
                'max:255',
                'required_with:provider',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $address = $this->input('structured_address', []);
            $hasAny = false;
            foreach (['venue_name', 'address_line_1', 'city', 'state_or_region', 'zip_or_postal_code', 'country'] as $key) {
                if (! empty($address[$key] ?? null)) {
                    $hasAny = true;
                    break;
                }
            }
            if (! $hasAny) {
                $validator->errors()->add(
                    'structured_address',
                    __('Provide at least one address field (venue, street, city, or country).'),
                );
            }
        });
    }
}
