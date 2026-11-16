<?php

namespace HiEvents\Http\Request\OrganizerSubscription;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use Illuminate\Validation\Rule;

class SubscribeToOrganizerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'locale' => ['sometimes', 'string', Rule::in(Locale::getSupportedLocales())],
        ];
    }
}
