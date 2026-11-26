<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class PreviewCampaignRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:200'],
            'preheader' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:100000'],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'early_access_product_ids' => ['nullable', 'array', 'max:50'],
            'early_access_product_ids.*' => ['integer', 'min:1'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
