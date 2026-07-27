<?php

namespace HiEvents\Http\Request\Waitlist;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class OfferWaitlistEntryRequest extends BaseRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id');

        return [
            'product_price_id' => ['required_without:entry_id', 'integer', 'exists:product_prices,id'],
            'entry_id' => ['required_without:product_price_id', 'integer', 'exists:waitlist_entries,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'event_occurrence_id' => [
                'nullable',
                'integer',
                Rule::exists('event_occurrences', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
