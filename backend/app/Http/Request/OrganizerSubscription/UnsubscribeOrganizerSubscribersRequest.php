<?php

namespace HiEvents\Http\Request\OrganizerSubscription;

use HiEvents\Http\Request\BaseRequest;

class UnsubscribeOrganizerSubscribersRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'integer', 'min:1']];
    }
}
