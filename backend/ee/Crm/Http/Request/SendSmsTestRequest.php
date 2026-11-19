<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\MobilePhoneNumberRule;

class SendSmsTestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'phone' => MobilePhoneNumberRule::rules(required: true),
        ];
    }
}
