<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UnsubscribeContactRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'channel' => ['nullable', Rule::in(CrmMarketingChannel::valuesArray())],
        ];
    }
}
