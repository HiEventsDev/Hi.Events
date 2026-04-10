<?php

namespace HiEvents\Http\Request\Attendee;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class CreateAttendeeRequest extends BaseRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id');

        return [
            'product_id' => ['int', 'required'],
            'event_occurrence_id' => ['int', 'nullable', Rule::exists('event_occurrences', 'id')->where('event_id', $eventId)->whereNull('deleted_at')],
            'product_price_id' => ['int', 'nullable'],
            'email' => ['required', 'email'],
            'first_name' => ['string', 'required', 'max:40'],
            'last_name' => ['string', 'max:40'],
            'amount_paid' => ['required', ...RulesHelper::MONEY],
            'send_confirmation_email' => ['required', 'boolean'],
            'taxes_and_fees' => ['array'],
            'taxes_and_fees.*.tax_or_fee_id' => ['required', 'int'],
            'taxes_and_fees.*.amount' => ['required', ...RulesHelper::MONEY],
            'locale' => ['required', Rule::in(Locale::getSupportedLocales())],
        ];
    }
}
