<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class ExportSelectedContactsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'contact_ids' => ['required', 'array', 'min:1', 'max:'.BulkContactsRequest::MAX_SELECTED_IDS],
            'contact_ids.*' => ['integer', 'min:1'],
        ];
    }
}
