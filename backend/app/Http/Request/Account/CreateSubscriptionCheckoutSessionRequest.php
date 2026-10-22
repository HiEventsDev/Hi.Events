<?php

namespace HiEvents\Http\Request\Account;

use HiEvents\DomainObjects\Enums\SubscriptionBillingInterval;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateSubscriptionCheckoutSessionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'interval' => ['required', Rule::enum(SubscriptionBillingInterval::class)],
        ];
    }
}
