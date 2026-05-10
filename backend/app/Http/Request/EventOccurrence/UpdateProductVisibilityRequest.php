<?php

namespace HiEvents\Http\Request\EventOccurrence;

use HiEvents\Http\Request\BaseRequest;

class UpdateProductVisibilityRequest extends BaseRequest
{
    public function rules(): array
    {
        // `min:1` is the load-bearing rule here. The storefront treats an
        // occurrence with zero visibility rows as "all products visible" — that
        // matches ProductFilterService::filterByOccurrenceVisibility's default
        // behaviour and is the right behaviour for "no rules saved yet". But
        // it means saving an explicitly-empty selection is a footgun: the
        // handler deletes every existing row, creates none, and the storefront
        // flips from "hide all of these products" to "show all of them".
        // Organizers who really want zero products available on a date should
        // cancel the occurrence instead.
        return [
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.min' => __('Select at least one product. To make a date inaccessible, cancel it from the schedule instead.'),
        ];
    }
}
