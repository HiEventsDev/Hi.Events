<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsComposer;
use HiEvents\Http\Request\BaseRequest;

class PreviewSmsCampaignRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:'.CampaignSmsComposer::MAX_BODY_LENGTH],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'early_access_product_ids' => ['nullable', 'array', 'max:50'],
            'early_access_product_ids.*' => ['integer', 'min:1'],
            'campaign_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
