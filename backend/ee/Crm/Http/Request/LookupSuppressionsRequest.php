<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class LookupSuppressionsRequest extends BaseRequest
{
    public const MAX_ADDRESSES = 50;

    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1', 'max:'.self::MAX_ADDRESSES],
            'emails.*' => ['required', 'string', 'max:254'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.max' => __('Check up to :max addresses at a time.', ['max' => self::MAX_ADDRESSES]),
        ];
    }
}
