<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\Http\Request\BaseRequest;

class SendCampaignRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
