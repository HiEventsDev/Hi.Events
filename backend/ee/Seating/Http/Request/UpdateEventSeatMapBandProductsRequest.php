<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Request;

use HiEvents\Enterprise\Seating\Services\Domain\SeatMapLayoutValidator;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Validator;

class UpdateEventSeatMapBandProductsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'band_products' => ['present', 'array', 'max:'.SeatMapLayoutValidator::MAX_BANDS],
            'band_products.*.band_key' => ['required', 'string', 'max:24'],
            'band_products.*.products' => ['present', 'array'],
            'band_products.*.products.*.product_id' => ['required', 'integer'],
            'band_products.*.products.*.price_adjustment' => ['sometimes', 'integer', 'min:-10000000', 'max:10000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bands = $this->input('band_products');
            if (! is_array($bands)) {
                return;
            }

            $seen = [];
            foreach ($bands as $index => $band) {
                $bandKey = is_array($band) ? ($band['band_key'] ?? null) : null;
                if (! is_string($bandKey)) {
                    continue;
                }

                if (isset($seen[$bandKey])) {
                    $validator->errors()->add("band_products.$index.band_key", __('Each band can only be listed once.'));
                }

                $seen[$bandKey] = true;
            }
        });
    }
}
