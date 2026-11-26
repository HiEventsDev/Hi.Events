<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class MergeContactsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'source_contact_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
